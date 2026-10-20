<?php

namespace Modules\RadioManagement\Services;

use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCheckState;
use Modules\RadioManagement\Models\RadioType;
use Modules\SharePointDocs\Services\MenuAccess;

/**
 * "Perlu Perhatian": the results of the register's checks (RadioCheckService, worked out on every request) as items to
 * deal with. An item is one radio, or one SN / Radio ID that several radios share. It can be given to somebody who may
 * change radios, set aside with a reason (until the data of its radios changes: then it is back), or fixed. A fix goes
 * through RadioService, which writes the radio's history and the audit trail.
 *
 * A refused change is a ValidationException carrying the message to show, as in RadioService.
 */
class AttentionInbox
{
    /** The checks whose item is a value several radios share, not one radio. */
    public const SHARED = ['sn_duplicate', 'id_duplicate'];

    /** The number on the sidebar entry is kept a minute; a change made here forgets it at once. */
    public const COUNT_CACHE = 'attention-open-count';

    /** The route a fix needs on top of this page: changing a radio of the register. */
    public const RADIO_UPDATE = 'radio-management.radio.update';

    /**
     * The checks, as they are now. RadioCheckService keeps what it found (the duplicated SNs and IDs) for as long as it
     * lives: every question asked of this class starts from a new one (in tests, and under Octane, the controller and
     * what it holds live from one request to the next).
     */
    private RadioCheckService $checks;

    public function __construct(
        private RadioService $radios,
        private RadioIdService $ids,
        private RadioConflicts $conflicts,
    ) {
        $this->checks = new RadioCheckService();
    }

    /** Starts from the register as it is now (see $checks). */
    private function fresh(): void
    {
        $this->checks = new RadioCheckService();
    }

    /**
     * The items of one check: subject => its radios, the oldest first (the radio's id, or the SN / Radio ID they share).
     * $subjects narrows it to those items. (A subject that is a number is an integer key of the array.)
     *
     * @param array<int, string>|null $subjects
     * @return array<string, Collection<int, Radio>>
     */
    public function items(string $check, ?array $subjects = null): array
    {
        $query = $this->checks->applyIssueFilter(Radio::query(), $check)->with(['radioType', 'department', 'channelTemplate']);
        if ($subjects !== null) {
            match ($check) {
                // strval: an SN of digits would be compared as a number (RadioCheckService says why).
                'sn_duplicate' => $query->whereIn('serial_number', array_map('strval', $subjects) ?: ['']),
                'id_duplicate' => $query->whereIntegerInRaw('dmr_id', array_map('intval', $subjects) ?: [0]),
                default => $query->whereIntegerInRaw('radios.id', array_map('intval', $subjects) ?: [0]),
            };
        }

        $items = [];
        foreach ($query->orderBy('radios.id')->get() as $radio) {
            $items[$this->subjectOf($check, $radio)][] = $radio;
        }

        return array_map(fn (array $radios) => collect($radios), $items);
    }

    /** The radios of one item, as they are now; null when it is not an item (any more). */
    public function item(string $check, string $subject): ?Collection
    {
        if (! isset(RadioCheckService::ISSUES[$check]) || $subject === '') {
            return null;
        }
        $this->fresh();

        return $this->items($check, [$subject])[$subject] ?? null;
    }

    /** The radios of an item as they are: an item set aside stays aside while this does not change. */
    public static function fingerprint(Collection $radios): string
    {
        return sha1($radios->sortBy('id')->map(fn (Radio $radio) => $radio->id . ':' . $radio->version())->implode('|'));
    }

    /**
     * The open items of every check (the ones set aside that still hold are not counted).
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $this->fresh();
        $aside = RadioCheckState::ready()
            ? RadioCheckState::query()->whereNotNull('dismissed_at')->get()->groupBy('check_code')
            : collect();

        $counts = [];
        foreach (array_keys(RadioCheckService::ISSUES) as $check) {
            $total = match ($check) {
                'sn_duplicate' => count($this->checks->duplicateSerials()),
                'id_duplicate' => count($this->checks->duplicateIds()),
                default => $this->checks->applyIssueFilter(Radio::query(), $check)->count(),
            };
            $states = $aside->get($check, collect());
            if ($states->isNotEmpty()) {
                $items = $this->items($check, $states->pluck('subject')->all());
                $total -= $states->filter(fn (RadioCheckState $state) => isset($items[$state->subject]) && $this->setAside($state, $items[$state->subject]))->count();
            }
            $counts[$check] = max(0, $total);
        }

        return $counts;
    }

    /** The number on the sidebar entry: every open item. */
    public static function openCount(): int
    {
        return (int) Cache::remember(self::COUNT_CACHE, 60, fn () => array_sum(app(self::class)->counts()));
    }

    /**
     * The open items of one check, for the list: all of them, or the ones given to $user ('mine') or to nobody ('none').
     * Seeing the check writes down when an item was first seen, and forgets the items that are gone.
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $check, string $who, ?User $user): array
    {
        $this->fresh();
        $items = $this->items($check);
        $states = $this->seen($check, array_map('strval', array_keys($items)));

        $rows = [];
        foreach ($items as $subject => $radios) {
            $state = $states->get((string) $subject);
            if ($this->setAside($state, $radios)
                || ($who === 'mine' && $state?->assigned_to !== $user?->id)
                || ($who === 'none' && $state?->assigned_to !== null)) {
                continue;
            }
            $rows[] = $this->entry($check, (string) $subject, $radios, $state);
        }

        return $rows;
    }

    /** One item for the panel at the right: what is wrong, its radios side by side, the fixes, who has it. */
    public function detail(string $check, string $subject): ?array
    {
        $radios = $this->item($check, $subject);
        if ($radios === null) {
            return null;
        }
        $state = $this->stateOf($check, $subject);
        $aside = $this->setAside($state, $radios);

        return $this->entry($check, $subject, $radios, $state) + [
            'label' => RadioCheckService::ISSUES[$check]['label'],
            'color' => RadioCheckService::ISSUES[$check]['color'],
            'title' => $this->title($check, $subject, $radios),
            'note' => $this->note($check, $radios->first()),
            'dismissed' => $aside ? [
                'reason' => $state->dismissed_reason,
                'by' => $state->dismisser?->name,
                'at' => $state->dismissed_at?->format('Y-m-d H:i'),
            ] : null,
            'records' => $this->records($check, $radios),
            'options' => $this->options($check, $radios),
        ];
    }

    /**
     * The people an item can be given to: active accounts of people (not API clients) who may change radios.
     *
     * @return list<array{id: int, name: string}>
     */
    public function assignable(): array
    {
        return User::query()->people()->with('roles')->orderBy('name')->get()
            ->filter(fn (User $user) => $user->isActive() && MenuAccess::allows($user, self::RADIO_UPDATE, 'Update'))
            ->map(fn (User $user) => ['id' => (int) $user->id, 'name' => (string) $user->name])
            ->values()->all();
    }

    /** Gives the item to somebody who may change radios, or to nobody ($userId null). */
    public function assign(string $check, string $subject, ?int $userId, User $actor): ?User
    {
        $radios = $this->item($check, $subject) ?? $this->refuse(self::GONE);
        $assignee = null;
        if ($userId !== null) {
            $assignee = User::query()->people()->with('roles')->whereKey($userId)->first();
            if ($assignee === null || ! $assignee->isActive() || ! MenuAccess::allows($assignee, self::RADIO_UPDATE, 'Update')) {
                $this->refuse('Item hanya bisa ditugaskan ke akun aktif yang boleh mengubah data radio.');
            }
        }

        $state = $this->stateFor($check, $subject);
        $before = $state->assigned_to ? $state->assignee?->name : null;
        $state->forceFill(['assigned_to' => $assignee?->id, 'assigned_by' => $actor->id, 'assigned_at' => now()])->save();
        $this->audit(AuditLogger::ATTENTION_ASSIGNED, $check, $subject, $radios,
            ['Ditugaskan ke' => $before], ['Ditugaskan ke' => $assignee?->name], $actor);

        return $assignee;
    }

    /** Sets the item aside, with the reason: it is back when the data of its radios changes. */
    public function dismiss(string $check, string $subject, string $reason, User $actor): void
    {
        $radios = $this->item($check, $subject) ?? $this->refuse(self::GONE);
        $this->stateFor($check, $subject)->forceFill([
            'dismissed_reason' => trim($reason),
            'dismissed_by' => $actor->id,
            'dismissed_at' => now(),
            'dismissed_fingerprint' => self::fingerprint($radios),
        ])->save();
        Cache::forget(self::COUNT_CACHE);
        $this->audit(AuditLogger::ATTENTION_DISMISSED, $check, $subject, $radios, null, ['Alasan' => trim($reason)], $actor);
    }

    /**
     * Applies one of the fixes of an item (options(): "sn:12", "verify:12" ...), with what was typed or chosen for it,
     * through RadioService. Returns what was done, in words.
     */
    public function fix(string $check, string $subject, string $option, mixed $value, User $actor): string
    {
        $radios = $this->item($check, $subject) ?? $this->refuse(self::GONE);
        $offered = array_column($this->options($check, $radios), 'id');
        [$kind, $radioId] = array_pad(explode(':', $option, 2), 2, null);
        if ($kind === 'dismiss' || ! in_array($option, $offered, true)) {
            $this->refuse('Perbaikan itu tidak ada untuk item ini. Muat ulang.');
        }
        /** @var Radio $radio */
        $radio = $radios->firstWhere('id', (int) $radioId);
        $name = $radio->display_name;
        $text = trim((string) $value);
        $today = now()->toDateString();

        $done = match ($kind) {
            'sn' => $this->newSerial($radio, $text, $actor),
            'archive' => $this->archiveDuplicate($radio, $radios, $check, $actor),
            'next_id', 'previous_id' => $this->newId($radio, $kind, $check, $actor),
            'type' => $this->newType($radio, $text, $actor),
            'ba' => $this->lostBa($radio, $text, false, $actor),
            'ba_archive' => $this->lostBa($radio, $text, true, $actor),
            'program' => $this->radios->addHistory($radio, ['action' => 'Program', 'event_date' => $today,
                'description' => 'Diprogram ulang' . ($radio->channelTemplate ? " ke template {$radio->channelTemplate->name}" : '') . ' (Perlu Perhatian).'], $actor->id)
                ? "Program hari ini dicatat untuk radio {$name}." : '',
            'verify' => $this->radios->addHistory($radio, ['action' => 'Verifikasi', 'event_date' => $today,
                'description' => 'Diverifikasi fisik (Perlu Perhatian).'], $actor->id)
                ? "Verifikasi hari ini dicatat untuk radio {$name}." : '',
            default => $this->radios->update($radio, ['needs_review' => false], $actor->id)
                ? "Radio {$name} ditandai sudah ditinjau." : '',
        };
        Cache::forget(self::COUNT_CACHE);

        return $done;
    }

    private const GONE = 'Item ini sudah tidak ada: datanya sudah dibetulkan atau berubah. Muat ulang.';

    private function newSerial(Radio $radio, string $typed, User $actor): string
    {
        $serial = RadioConflicts::normalizeSerial($typed);
        if ($serial === '') {
            $this->refuse('Ketik SN yang benar.');
        }
        if (mb_strlen($serial) > 50) {
            $this->refuse('SN paling panjang 50 karakter.');
        }
        if ($serial === RadioConflicts::normalizeSerial($radio->serial_number)) {
            $this->refuse('SN itu sama dengan yang sekarang.');
        }
        $name = $radio->display_name;
        $this->radios->update($radio, ['serial_number' => $serial], $actor->id);

        return "SN radio {$name} sekarang {$serial}.";
    }

    private function archiveDuplicate(Radio $radio, Collection $radios, string $check, User $actor): string
    {
        $keeper = $radios->first();
        $what = $check === 'id_duplicate' ? "Radio ID {$radio->dmr_id}" : "SN {$radio->serial_number}";
        $name = $radio->display_name;
        $this->radios->archive($radio, 'Lainnya', now()->toDateString(), "Duplikat {$what} dari {$keeper->display_name} (Perlu Perhatian).", $actor->id);

        return "Radio {$name} diarsipkan sebagai duplikat.";
    }

    private function newId(Radio $radio, string $kind, string $check, User $actor): string
    {
        if ($kind === 'previous_id') {
            $id = (int) $radio->previous_dmr_id;
            if ($id < 1 || $this->conflicts->radioId($id, $radio->id) !== null) {
                $this->refuse("Radio ID {$id} sudah tidak bebas. Pilih ID bebas berikutnya.");
            }
        } else {
            $id = $this->freeIdFor($radio, $check);
            if ($id === null) {
                $this->refuse('Tidak ada Radio ID bebas.');
            }
        }
        $this->radios->update($radio, ['dmr_id' => $id], $actor->id);

        return "Radio {$radio->serial_number} sekarang memakai Radio ID {$id} (MDC " . RadioIdService::mdc($id) . '). Program ulang radionya ke ID itu.';
    }

    private function newType(Radio $radio, string $typed, User $actor): string
    {
        $type = ctype_digit($typed) ? RadioType::query()->whereKey((int) $typed)->first() : null;
        if ($type === null) {
            $this->refuse('Pilih tipe radio.');
        }
        $name = $radio->display_name;
        $this->radios->update($radio, ['radio_type_id' => $type->id], $actor->id);

        return "Tipe radio {$name} sekarang {$type->name}.";
    }

    private function lostBa(Radio $radio, string $typed, bool $archive, User $actor): string
    {
        if ($typed === '') {
            $this->refuse('Ketik No. BA kehilangan.');
        }
        if (mb_strlen($typed) > 100) {
            $this->refuse('No. BA paling panjang 100 karakter.');
        }
        $name = $radio->display_name;
        DB::transaction(function () use ($radio, $typed, $archive, $actor) {
            $this->radios->update($radio, ['document_no' => $typed], $actor->id);
            if ($archive) {
                $this->radios->archive($radio, 'Hilang (BA selesai)', now()->toDateString(), "BA {$typed} (Perlu Perhatian).", $actor->id);
            }
        });

        return $archive ? "Radio {$name} diarsipkan sebagai hilang (BA {$typed})." : "No. BA {$typed} dicatat untuk radio {$name}.";
    }

    /** The Radio ID a radio is offered: the first that can be used above its own (one with F), else the lowest free one. */
    private function freeIdFor(Radio $radio, string $check): ?int
    {
        $after = $check === 'id_hex_f' ? (int) $radio->dmr_id : null;

        return $this->ids->nextFree(1, $radio->id, $after)[0] ?? null;
    }

    /**
     * The fixes of an item, in the order they are offered: {id, title, detail, field?}. For radios that share an SN or a
     * Radio ID the oldest is taken to be the right one: the fixes are for the others.
     *
     * @return list<array<string, mixed>>
     */
    private function options(string $check, Collection $radios): array
    {
        /** @var Radio $first */
        $first = $radios->first();
        $short = fn (Radio $radio) => $radio->dmr_id ? (string) $radio->dmr_id : ($radio->serial_number ?: '#' . $radio->id);
        $archive = fn (Radio $radio) => [
            'id' => "archive:{$radio->id}",
            'title' => "Arsipkan radio {$short($radio)} sebagai duplikat",
            'detail' => 'Riwayatnya tetap ada' . ($radio->dmr_id ? " dan Radio ID {$radio->dmr_id} dilepas." : '.'),
        ];
        $free = fn (Radio $radio, string $title, string $detail) => ($id = $this->freeIdFor($radio, $check)) === null ? [] : [[
            'id' => "next_id:{$radio->id}",
            'title' => str_replace(':id', $id . ' (MDC ' . RadioIdService::mdc($id) . ')', $title),
            'detail' => $detail,
        ]];

        $options = [];
        switch ($check) {
            case 'sn_duplicate':
                foreach ($radios->slice(1) as $radio) {
                    $options[] = ['id' => "sn:{$radio->id}", 'title' => "Betulkan SN radio {$short($radio)}",
                        'detail' => 'Cek stiker di radionya, lalu ketik SN yang benar.',
                        'field' => ['kind' => 'text', 'mono' => true, 'value' => '', 'placeholder' => (string) $radio->serial_number, 'max' => 50]];
                    $options[] = $archive($radio);
                }
                $options[] = ['id' => 'dismiss', 'title' => 'Radio-radio ini memang berbeda', 'detail' => 'Abaikan cek ini, dengan alasannya.'];
                break;

            case 'id_duplicate':
                foreach ($radios->slice(1) as $radio) {
                    array_push($options, ...$free($radio, "Beri radio {$radio->serial_number} ID bebas berikutnya, :id", 'Radionya perlu diprogram ulang ke ID itu.'));
                    $options[] = $archive($radio);
                }
                break;

            case 'id_hex_f':
                array_push($options, ...$free($first, 'Pakai ID berikutnya yang bisa dipakai, :id', 'Radionya harus diprogram ulang ke ID itu.'));
                break;

            case 'sn_empty':
            case 'sn_format':
                $suggestion = $check === 'sn_format' ? (string) preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $first->serial_number)) : '';
                $options[] = ['id' => "sn:{$first->id}", 'title' => $check === 'sn_empty' ? 'Isi SN' : 'Betulkan SN',
                    'detail' => 'Format umum: 3 angka, 3 huruf, 4 karakter, mis. 867TZB5309.',
                    'field' => ['kind' => 'text', 'mono' => true, 'value' => $suggestion, 'placeholder' => '867TZB5309', 'max' => 50]];
                break;

            case 'id_empty':
                $previous = (int) $first->previous_dmr_id;
                if ($previous > 0 && $this->conflicts->radioId($previous, $first->id) === null) {
                    $options[] = ['id' => "previous_id:{$first->id}", 'title' => "Kembalikan ID terakhirnya, {$previous} (MDC " . RadioIdService::mdc($previous) . ')',
                        'detail' => 'ID itu masih bebas.'];
                }
                array_push($options, ...$free($first, 'Beri ID bebas berikutnya, :id', 'Radionya perlu diprogram ke ID itu.'));
                break;

            case 'type_empty':
                $options[] = ['id' => "type:{$first->id}", 'title' => 'Pilih tipe radio', 'detail' => 'Dari master Tipe Radio yang aktif.',
                    'field' => ['kind' => 'select', 'value' => '', 'choices' => RadioType::query()->where('is_active', true)->orderBy('name')
                        ->get(['id', 'name'])->map(fn (RadioType $type) => ['value' => (string) $type->id, 'label' => $type->name])->values()->all()]];
                break;

            case 'lost_no_ba':
                $field = ['kind' => 'text', 'mono' => true, 'value' => '', 'placeholder' => 'BA2300412', 'max' => 100];
                $options[] = ['id' => "ba:{$first->id}", 'title' => 'Isi No. BA kehilangan', 'detail' => 'Radionya tetap aktif dengan lokasi Hilang.', 'field' => $field];
                $options[] = ['id' => "ba_archive:{$first->id}", 'title' => 'Isi No. BA, lalu arsipkan sebagai hilang',
                    'detail' => 'Alasan arsip "Hilang (BA selesai)"' . ($first->dmr_id ? "; Radio ID {$first->dmr_id} dilepas." : '.'), 'field' => $field];
                break;

            case 'reprogram':
                $options[] = ['id' => "program:{$first->id}", 'title' => 'Catat program hari ini',
                    'detail' => $first->channelTemplate ? "Radio sudah diprogram ke template {$first->channelTemplate->name}." : 'Radio sudah diprogram ulang.'];
                break;

            case 'verify_empty':
            case 'verify_stale':
                $options[] = ['id' => "verify:{$first->id}", 'title' => 'Catat verifikasi hari ini', 'detail' => 'Radionya sudah dicek fisik hari ini.'];
                break;

            default: // needs_review
                $options[] = ['id' => "reviewed:{$first->id}", 'title' => 'Tandai sudah ditinjau', 'detail' => 'Data radionya sudah dicek dan benar.'];
        }

        return $options;
    }

    /** The item in the list: what it is, who has its radios, to whom it is given. */
    private function entry(string $check, string $subject, Collection $radios, ?RadioCheckState $state): array
    {
        /** @var Radio $first */
        $first = $radios->first();

        return [
            'check' => $check,
            'subject' => $subject,
            'head' => match ($check) {
                'sn_duplicate' => $subject,
                'id_duplicate' => "Radio ID {$subject}",
                default => $first->display_name,
            },
            'side' => match ($check) {
                'sn_duplicate' => 'Radio ' . self::andList($radios->map(fn (Radio $radio) => $radio->dmr_id ? (string) $radio->dmr_id : 'tanpa ID')),
                'id_duplicate' => $radios->count() . ' radio',
                'id_hex_f' => 'MDC ' . $first->mdc_code,
                'verify_empty' => 'Belum pernah',
                'verify_stale' => 'Terakhir ' . $first->last_verified_at?->format('d-m-Y'),
                'reprogram' => (string) $first->channelTemplate?->name,
                default => (string) $first->radioType?->name,
            },
            'who' => self::andList($radios->map(fn (Radio $radio) => trim((string) $radio->user_name) ?: '—')),
            'assignee' => $state?->assigned_to ? ['id' => (int) $state->assigned_to, 'name' => (string) $state->assignee?->name] : null,
            'first_seen' => $state?->first_seen_at?->toDateString(),
        ];
    }

    /** What the panel says is wrong, in one line. */
    private function title(string $check, string $subject, Collection $radios): string
    {
        $first = $radios->first();
        $name = $first->display_name;

        return match ($check) {
            'sn_duplicate' => "SN {$subject} dipakai {$radios->count()} radio",
            'id_duplicate' => "Radio ID {$subject} dipakai {$radios->count()} radio",
            'id_hex_f' => "Radio ID {$first->dmr_id} mengandung F (MDC {$first->mdc_code})",
            'sn_empty' => "Radio {$name} belum punya SN",
            'sn_format' => "SN {$first->serial_number} tidak berformat standar",
            'id_empty' => "Radio {$name} belum punya Radio ID",
            'type_empty' => "Tipe radio {$name} belum diisi",
            'lost_no_ba' => "Radio {$name} hilang tanpa No. BA",
            'reprogram' => "Radio {$name} perlu diprogram ulang",
            'verify_empty' => "Radio {$name} belum pernah diverifikasi",
            'verify_stale' => "Verifikasi radio {$name} lewat " . config('radio.verify_max_months') . ' bulan',
            default => "Radio {$name} perlu dicek",
        };
    }

    /** What else is known of it (the hint of the check: the notes of a review, the date of a template ...). */
    private function note(string $check, Radio $radio): ?string
    {
        $issue = collect($this->checks->issuesFor($radio))->firstWhere('code', $check);

        return $issue['hint'] ?? null;
    }

    /**
     * The radios of the item side by side: the fields that differ between them (or the field the check is about) marked,
     * the radios after the oldest of shared ones marked as the suspects.
     *
     * @return list<array<string, mixed>>
     */
    private function records(string $check, Collection $radios): array
    {
        $date = fn ($value) => $value?->format('d-m-Y') ?? 'Belum pernah';
        $fields = [
            'dmr_id' => ['Radio ID', fn (Radio $radio) => $radio->dmr_id ? $radio->dmr_id . ' · MDC ' . $radio->mdc_code : ($radio->isArchived() ? '— (diarsipkan)' : '—'), true],
            'serial_number' => ['SN', fn (Radio $radio) => $radio->serial_number ?: '—', true],
            'user_name' => ['User/Unit', fn (Radio $radio) => implode(' · ', array_filter([$radio->user_name, $radio->department?->name], 'filled')) ?: '—', false],
            'radio_type_id' => ['Tipe radio', fn (Radio $radio) => $radio->radioType?->name ?? '—', false],
            'placement' => ['Lokasi', fn (Radio $radio) => $radio->placement?->label() . ' · ' . $radio->condition?->label(), false],
            'last_verified_at' => ['Verifikasi terakhir', fn (Radio $radio) => $date($radio->last_verified_at), false],
            'last_programmed_at' => ['Program terakhir', fn (Radio $radio) => $date($radio->last_programmed_at), false],
            'document_no' => ['No. Dokumen', fn (Radio $radio) => $radio->document_no ?: '—', true],
        ];
        $about = match ($check) {
            'sn_empty', 'sn_format', 'sn_duplicate' => ['serial_number'],
            'id_empty', 'id_hex_f', 'id_duplicate' => ['dmr_id'],
            'type_empty' => ['radio_type_id'],
            'lost_no_ba' => ['placement', 'document_no'],
            'reprogram' => ['last_programmed_at'],
            'verify_empty', 'verify_stale' => ['last_verified_at'],
            default => [],
        };
        $shared = $radios->count() > 1;
        $differs = [];
        foreach ($fields as $field => [, $show]) {
            $differs[$field] = $shared && $radios->map($show)->unique()->count() > 1;
        }

        return $radios->values()->map(fn (Radio $radio, int $index) => [
            'id' => $radio->id,
            'head' => 'Radio ' . ($radio->dmr_id ?: ($radio->serial_number ?: '#' . $radio->id)),
            'tag' => $radio->isArchived() ? 'Diarsipkan ' . $radio->archived_at?->format('d-m-Y') : 'Dibuat ' . $radio->created_at?->format('d-m-Y'),
            'suspect' => $shared && $index > 0,
            'rows' => array_values(array_map(fn (string $field) => [
                'label' => $fields[$field][0],
                'value' => (string) $fields[$field][1]($radio),
                'mono' => $fields[$field][2],
                'marked' => $shared ? $differs[$field] : in_array($field, $about, true),
            ], array_keys($fields))),
        ])->all();
    }

    /** "a", "a dan b", "a, b dan c" (at most four, then "dan N lagi"). */
    private static function andList(Collection $words): string
    {
        $words = $words->values();
        if ($words->count() > 4) {
            return $words->take(3)->implode(', ') . ' dan ' . ($words->count() - 3) . ' lagi';
        }
        if ($words->count() < 2) {
            return (string) $words->first();
        }

        return $words->slice(0, -1)->implode(', ') . ' dan ' . $words->last();
    }

    private function subjectOf(string $check, Radio $radio): string
    {
        return match ($check) {
            'sn_duplicate' => mb_strtoupper(trim((string) $radio->serial_number)),
            'id_duplicate' => (string) $radio->dmr_id,
            default => (string) $radio->id,
        };
    }

    private function setAside(?RadioCheckState $state, Collection $radios): bool
    {
        return $state !== null && $state->dismissed_at !== null && $state->dismissed_fingerprint === self::fingerprint($radios);
    }

    private function stateOf(string $check, string $subject): ?RadioCheckState
    {
        return RadioCheckState::ready()
            ? RadioCheckState::query()->with(['assignee', 'dismisser'])->where('check_code', $check)->where('subject', $subject)->first()
            : null;
    }

    /** The state of an item, made when it has none yet (an item that was never listed). */
    private function stateFor(string $check, string $subject): RadioCheckState
    {
        if (! RadioCheckState::ready()) {
            $this->refuse('Tabel Perlu Perhatian belum dibuat di database. Jalankan "php artisan migrate" dulu.');
        }
        try {
            return RadioCheckState::query()->with('assignee')->firstOrCreate(['check_code' => $check, 'subject' => $subject], ['first_seen_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            // Made by another request at the same moment.
            return RadioCheckState::query()->with('assignee')->where('check_code', $check)->where('subject', $subject)->firstOrFail();
        }
    }

    /**
     * The states of the items of one check, keyed by subject. The items seen for the first time get one (when they were
     * first seen); the states of the items that are gone go.
     *
     * @param array<int, string> $subjects
     * @return Collection<string, RadioCheckState>
     */
    private function seen(string $check, array $subjects): Collection
    {
        if (! RadioCheckState::ready()) {
            return collect();
        }
        // (A plain collection: the except() of Eloquent's goes by the models' ids, not by the keys.)
        $load = fn () => RadioCheckState::query()->with('assignee')->where('check_code', $check)->get()
            ->keyBy(fn (RadioCheckState $state) => (string) $state->subject)->toBase();
        $states = $load();

        $gone = $states->except($subjects)->pluck('id')->all();
        foreach (array_chunk($gone, 500) as $ids) {
            RadioCheckState::query()->whereIntegerInRaw('id', $ids)->delete();
        }
        $new = array_values(array_diff($subjects, $states->keys()->all()));
        $now = now();
        foreach (array_chunk($new, 200) as $chunk) {
            try {
                RadioCheckState::query()->insert(array_map(fn (string $subject) => [
                    'check_code' => $check, 'subject' => $subject, 'first_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            } catch (UniqueConstraintViolationException) {
                // Another request saw them at the same moment: theirs are as good.
            }
        }

        return $gone || $new ? $load() : $states;
    }

    private function audit(string $action, string $check, string $subject, Collection $radios, ?array $old, ?array $new, User $actor): void
    {
        $radio = in_array($check, self::SHARED, true) ? null : $radios->first();
        $label = RadioCheckService::ISSUES[$check]['label'] . ': ' . match ($check) {
            'sn_duplicate' => "SN {$subject}",
            'id_duplicate' => "Radio ID {$subject}",
            default => $radio->display_name,
        };
        app(AuditLogger::class)->log($action, $radio, $old, $new, $actor, $label);
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['item' => $message]);
    }
}
