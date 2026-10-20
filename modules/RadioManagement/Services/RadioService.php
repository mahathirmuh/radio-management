<?php

namespace Modules\RadioManagement\Services;

use App\Services\Audit\AuditLogger;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioType;

/**
 * Every change to a radio goes through here so it lands in the history log
 * ("Riwayat") with a readable before/after, and in the audit trail (menu "Audit Log").
 *
 * Writes run one at a time (RegisterLock) and start from the radio's current row, so two
 * people saving at the same moment cannot hand out one Radio ID twice or undo each other.
 * A refused save is a ValidationException carrying the message to show.
 */
class RadioService
{
    /** Fields that are worth a history line, with their display label. */
    public const TRACKED = [
        'dmr_id' => 'Radio ID',
        'serial_number' => 'SN',
        'radio_type_id' => 'Tipe',
        'owner_company_id' => 'Pemilik Aset',
        'user_name' => 'User/Unit',
        'department_id' => 'Department',
        'company_id' => 'Company',
        'channel_template_id' => 'Template',
        'last_programmed_at' => 'Tgl Program',
        'placement' => 'Posisi',
        'condition' => 'Kondisi',
        'last_verified_at' => 'Tgl Verifikasi',
        'document_no' => 'No. Dokumen',
        'notes' => 'Keterangan',
        'needs_review' => 'Perlu dicek',
    ];

    private const HANDOVER_FIELDS = ['user_name', 'company_id', 'department_id'];

    private const STATUS_FIELDS = ['placement', 'condition'];

    /** Master data a radio points at: field => [model, label]. */
    private const MASTERS = [
        'radio_type_id' => [RadioType::class, 'Tipe Radio'],
        'owner_company_id' => [RadioCompany::class, 'Pemilik Aset'],
        'department_id' => [RadioDepartment::class, 'Department'],
        'company_id' => [RadioCompany::class, 'Company'],
        'channel_template_id' => [ChannelTemplate::class, 'Template Channel'],
    ];

    public function __construct(
        private RadioIdService $ids,
        private RadioConflicts $conflicts,
        private RegisterLock $lock,
    ) {
    }

    public function create(array $data, ?int $userId): Radio
    {
        return $this->lock->run(function () use ($data, $userId) {
            $this->guardConflicts($data);

            $radio = Radio::create($data + ['created_by' => $userId, 'updated_by' => $userId]);
            $this->log($radio, 'Dibuat', 'Radio didaftarkan.', $userId);
            $this->audit(AuditLogger::RADIO_CREATED, $radio, new: $this->facts($radio));

            return $radio;
        });
    }

    /**
     * @param string|null $version Radio::version() as it was when the edit form was opened. A form
     *                             based on an older state is refused: it would silently put back
     *                             the values someone else has just changed.
     */
    public function update(Radio $radio, array $data, ?int $userId, ?string $version = null): Radio
    {
        return $this->lock->run(function () use ($radio, $data, $userId, $version) {
            $this->reload($radio);
            if ($version !== null && $version !== $radio->version()) {
                throw StaleRadio::withMessages(['radio' => 'Data radio ini baru saja diubah'
                    . ($radio->updater ? ' oleh ' . $radio->updater->name : '')
                    . ' (' . $radio->updated_at?->format('d-m-Y H:i') . '). '
                    . 'Tutup form ini, buka lagi, lalu ulangi perubahan Anda.']);
            }
            if ($radio->isArchived()) {
                unset($data['dmr_id']); // archived radios do not hold an ID
            }
            $this->guardConflicts($data, $radio);

            $radio->fill($data);
            $changes = $this->pendingChanges($radio);
            $radio->updated_by = $userId;
            $radio->save();

            if ($changes) {
                [$action, $eventDate] = $this->classify($changes, $radio);
                $this->log($radio, $action, $this->describe($changes), $userId, [
                    'event_date' => $eventDate,
                    'changes' => $changes,
                    'document_no' => $action === 'Serah Terima' ? $radio->document_no : null,
                ]);
                $this->audit(AuditLogger::RADIO_UPDATED, $radio,
                    array_column($changes, 'old', 'label'), array_column($changes, 'new', 'label'));
            }

            return $radio;
        });
    }

    /** Take a radio out of service; its Radio ID is released for reuse. */
    public function archive(Radio $radio, string $reason, string $date, ?string $note, ?int $userId): Radio
    {
        return $this->lock->run(function () use ($radio, $reason, $date, $note, $userId) {
            $this->reload($radio);
            if ($radio->isArchived()) {
                $this->refuse('Radio ini sudah diarsipkan.');
            }

            $releasedId = $radio->dmr_id;
            $radio->forceFill([
                'previous_dmr_id' => $releasedId ?? $radio->previous_dmr_id,
                'dmr_id' => null,
                'archived_at' => $date,
                'archive_reason' => $reason,
                'updated_by' => $userId,
            ])->save();

            $description = "Diarsipkan: {$reason}.";
            if ($releasedId) {
                $description .= " Radio ID {$releasedId} dilepas.";
            }
            if (filled($note)) {
                $description .= ' ' . $note;
            }
            $this->log($radio, 'Arsip', $description, $userId, ['event_date' => $date]);
            $this->audit(AuditLogger::RADIO_ARCHIVED, $radio,
                $releasedId ? ['Radio ID' => (string) $releasedId] : null,
                ($releasedId ? ['Radio ID' => null] : []) + array_filter([
                    'Alasan' => $reason,
                    'Tanggal' => Carbon::parse($date)->format('d-m-Y'),
                    'Catatan' => filled($note) ? $note : null,
                ], fn ($value) => $value !== null));

            return $radio;
        });
    }

    /** Bring an archived radio back; its old Radio ID returns unless another radio has it now. */
    public function restore(Radio $radio, ?int $userId): Radio
    {
        return $this->lock->run(function () use ($radio, $userId) {
            $this->reload($radio);
            if (! $radio->isArchived()) {
                $this->refuse('Radio ini tidak sedang diarsipkan.');
            }

            // The radio carried this ID until it was archived, so it gets it back as it was, also
            // an imported ID containing hex F (the "Cek" column keeps flagging that). Only an ID
            // that another radio took or reserved in the meantime stays away.
            $previous = $radio->previous_dmr_id;
            $idBack = $previous && ! $this->ids->isTaken($previous, $radio->id);

            $radio->forceFill([
                'archived_at' => null,
                'archive_reason' => null,
                'dmr_id' => $idBack ? $previous : null,
                'updated_by' => $userId,
            ])->save();

            $description = 'Radio dipulihkan dari arsip.';
            if ($previous) {
                $description .= $idBack
                    ? " Radio ID {$previous} dipakai kembali."
                    : " Radio ID lama {$previous} sekarang dipakai atau dicadangkan radio lain, isi Radio ID baru.";
            }
            $this->log($radio, 'Dipulihkan', $description, $userId);
            $this->audit(AuditLogger::RADIO_RESTORED, $radio, new: ['Radio ID' => $radio->dmr_id ? (string) $radio->dmr_id : null, 'Keterangan' => $description]);

            return $radio;
        });
    }

    /**
     * Manual history entry. "Program" and "Verifikasi" entries also move the
     * radio's own date forward, so the register stays in step with the log.
     */
    public function addHistory(Radio $radio, array $data, ?int $userId): RadioHistory
    {
        return $this->lock->run(function () use ($radio, $data, $userId) {
            $this->reload($radio);
            $date = Carbon::parse($data['event_date'])->startOfDay();
            $field = match ($data['action']) {
                'Program' => 'last_programmed_at',
                'Verifikasi' => 'last_verified_at',
                default => null,
            };

            if ($field && (! $radio->{$field} || $radio->{$field}->lt($date))) {
                $radio->forceFill([$field => $date->toDateString(), 'updated_by' => $userId])->save();
            }

            $entry = $this->log($radio, $data['action'], $data['description'] ?? null, $userId, [
                'event_date' => $date->toDateString(),
                'document_no' => $data['document_no'] ?? null,
            ]);
            $this->audit(AuditLogger::RADIO_HISTORY_ADDED, $radio, new: array_filter([
                'Jenis' => $data['action'],
                'Tanggal' => $date->format('d-m-Y'),
                'Keterangan' => $data['description'] ?? null,
                'No. Dokumen' => $data['document_no'] ?? null,
            ], fn ($value) => filled($value)));

            return $entry;
        });
    }

    /**
     * Hand a radio over to someone (the dialog "Serah terima" of its detail). The radio is theirs from now on: their name,
     * and the department and company when they are given; where it is and in what condition; and the BA (with the PO) of
     * the handover is its document number. One line "Serah Terima" in its history, with the BA, and one in the audit
     * trail. The receiver's signature is not kept anywhere: it goes onto the printed form only (the controller checks it).
     *
     * @param array{receiver: string, employee_id?: ?string, ba: string, po?: ?string, accessories?: ?array<int, string>,
     *     condition?: ?string, placement?: ?string, department_id?: ?int, company_id?: ?int} $data
     */
    public function handover(Radio $radio, array $data, ?int $userId): RadioHistory
    {
        return $this->lock->run(function () use ($radio, $data, $userId) {
            $this->reload($radio);
            if ($radio->isArchived()) {
                $this->refuse('Radio yang diarsipkan tidak bisa diserahterimakan. Pulihkan dulu.');
            }

            $document = implode(' / ', array_filter([trim((string) ($data['po'] ?? '')), trim((string) $data['ba'])], 'filled'));
            $radio->fill(array_filter([
                'user_name' => trim((string) $data['receiver']),
                'department_id' => $data['department_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'placement' => $data['placement'] ?? Placement::DiUser->value,
                'condition' => $data['condition'] ?? null,
                'document_no' => $document,
            ], fn ($value) => $value !== null && $value !== ''));
            $changes = $this->pendingChanges($radio);
            $radio->updated_by = $userId;
            $radio->save();

            $accessories = array_values(array_filter((array) ($data['accessories'] ?? []), 'filled'));
            $employee = trim((string) ($data['employee_id'] ?? ''));
            $description = 'Diserahkan ke ' . trim((string) $data['receiver']) . ($employee !== '' ? " ({$employee})" : '') . '.'
                . ($accessories ? ' Aksesori: ' . implode(', ', $accessories) . '.' : ' Tanpa aksesori.');
            $entry = $this->log($radio, 'Serah Terima', $description, $userId, [
                'document_no' => $document,
                'changes' => $changes ?: null,
            ]);
            $this->audit(AuditLogger::RADIO_HANDED_OVER, $radio, new: array_filter([
                'Penerima' => trim((string) $data['receiver']),
                'No. karyawan' => $employee,
                'No. BA' => trim((string) $data['ba']),
                'No. PO' => trim((string) ($data['po'] ?? '')),
                'Aksesori' => implode(', ', $accessories),
                'Kondisi' => $radio->condition?->label(),
                'Posisi' => $radio->placement?->label(),
            ], 'filled'));

            return $entry;
        });
    }

    /** Remove a radio that was entered by mistake (soft delete; its history is kept). */
    public function delete(Radio $radio, ?int $userId): void
    {
        $this->lock->run(function () use ($radio, $userId) {
            $this->reload($radio);
            $this->log($radio, 'Dihapus', 'Data radio dihapus dari register.', $userId);
            $facts = $this->facts($radio);
            $radio->delete();
            $this->audit(AuditLogger::RADIO_DELETED, $radio, old: $facts);
        });
    }

    public function log(Radio $radio, string $action, ?string $description, ?int $userId, array $extra = []): RadioHistory
    {
        return RadioHistory::create([
            'radio_id' => $radio->id,
            'event_date' => $extra['event_date'] ?? now()->toDateString(),
            'action' => $action,
            'description' => $description,
            'document_no' => $extra['document_no'] ?? null,
            'changes' => $extra['changes'] ?? null,
            'user_id' => $userId,
        ]);
    }

    /** A line of the audit trail about the radio, by whoever is signed in. */
    private function audit(string $action, Radio $radio, ?array $old = null, ?array $new = null): void
    {
        app(AuditLogger::class)->log($action, $radio, $old, $new, label: $radio->display_name);
    }

    /**
     * The radio as the audit trail shows it when it is made or deleted: the fields worth a history line that are
     * filled in, as the register shows them.
     *
     * @return array<string, string>
     */
    private function facts(Radio $radio): array
    {
        $facts = [];
        foreach (self::TRACKED as $field => $label) {
            $value = $this->display($field, $radio->getAttribute($field));
            if ($value !== null) {
                $facts[$label] = $value;
            }
        }

        return $facts;
    }

    /** The row as it is now, not as it was when the request started waiting for the lock. */
    private function reload(Radio $radio): void
    {
        $radio->refresh();
        if ($radio->trashed()) {
            $this->refuse('Radio ini sudah dihapus dari register.');
        }
    }

    /**
     * Same checks as the form validation, repeated where no other save can slip in between.
     * A value that is not being changed is left alone: imported radios may carry a duplicated
     * or hex-F Radio ID and must stay editable.
     */
    private function guardConflicts(array $data, ?Radio $radio = null): void
    {
        $errors = [];

        $id = $data['dmr_id'] ?? null;
        if ($id !== null && $id !== '' && (int) $id !== (int) $radio?->dmr_id) {
            $errors['dmr_id'] = $this->conflicts->radioId((int) $id, $radio?->id);
        }

        $serial = $data['serial_number'] ?? null;
        if (filled($serial) && $serial !== RadioConflicts::normalizeSerial($radio?->serial_number)) {
            $errors['serial_number'] = $this->conflicts->serial((string) $serial, $radio?->id);
        }

        // The form checked these before this request got its turn; a master row deleted or
        // switched off in between must come back as a message, not as a foreign key error.
        foreach (self::MASTERS as $field => [$model, $label]) {
            $value = $data[$field] ?? null;
            if (blank($value) || (int) $value === (int) $radio?->{$field}) {
                continue;
            }
            $row = $model::query()->find($value);
            if (! $row) {
                $errors[$field] = "{$label} tidak ditemukan di master data.";
            } elseif (! $row->is_active) {
                $errors[$field] = "{$label} yang dipilih sudah dinonaktifkan di master data.";
            }
        }

        if ($errors = array_filter($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['radio' => $message]);
    }

    /** @return array<string, array{label: string, old: ?string, new: ?string}> */
    private function pendingChanges(Radio $radio): array
    {
        $changes = [];
        foreach (array_keys($radio->getDirty()) as $field) {
            if (! isset(self::TRACKED[$field])) {
                continue;
            }
            $old = $this->display($field, $radio->getOriginal($field));
            $new = $this->display($field, $radio->getAttribute($field));
            if ($old !== $new) {
                $changes[$field] = ['label' => self::TRACKED[$field], 'old' => $old, 'new' => $new];
            }
        }

        return $changes;
    }

    /** @return array{0: string, 1: string} action + event date */
    private function classify(array $changes, Radio $radio): array
    {
        $today = now()->toDateString();

        if (array_intersect(self::HANDOVER_FIELDS, array_keys($changes))) {
            return ['Serah Terima', $today];
        }
        if (array_intersect(self::STATUS_FIELDS, array_keys($changes))) {
            return ['Ubah Status', $today];
        }
        if (isset($changes['last_programmed_at'])) {
            return ['Program', $radio->last_programmed_at?->toDateString() ?? $today];
        }
        if (isset($changes['last_verified_at'])) {
            return ['Verifikasi', $radio->last_verified_at?->toDateString() ?? $today];
        }

        return ['Ubah Data', $today];
    }

    private function describe(array $changes): string
    {
        return collect($changes)
            ->map(fn (array $change) => sprintf('%s: %s → %s', $change['label'], $change['old'] ?? '-', $change['new'] ?? '-'))
            ->implode('; ');
    }

    private function display(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            $value instanceof BackedEnum => method_exists($value, 'label') ? $value->label() : (string) $value->value,
            $value instanceof CarbonInterface => $value->format('d-m-Y'),
            $field === 'needs_review' => $value ? 'Ya' : 'Tidak',
            $field === 'radio_type_id' => RadioType::find($value)?->name ?? (string) $value,
            $field === 'owner_company_id', $field === 'company_id' => RadioCompany::find($value)?->code ?? (string) $value,
            $field === 'department_id' => RadioDepartment::find($value)?->name ?? (string) $value,
            $field === 'channel_template_id' => ChannelTemplate::find($value)?->name ?? (string) $value,
            in_array($field, ['last_programmed_at', 'last_verified_at'], true) => Carbon::parse($value)->format('d-m-Y'),
            $field === 'notes' => mb_strimwidth((string) $value, 0, 80, '…'),
            default => (string) $value,
        };
    }
}
