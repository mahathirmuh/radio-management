<?php

namespace Modules\RadioManagement\Services;

use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioOpnameItem;
use Modules\RadioManagement\Models\RadioOpnameSession;
use Modules\RadioManagement\Models\ServiceTicket;

/**
 * Stock opname (menu "Stock Opname", on a phone): a session counts the radios of one department (of one company, or of
 * all). Each radio found is saved as it is scanned: who has it, in what condition. Sending the session verifies every
 * radio found today, through RadioBulk::verify (a line "Verifikasi" in its history), puts a condition that changed into
 * the register, and writes one line in the audit trail; what was not found, or is with somebody else, is listed for a
 * person to decide (K13f).
 *
 * A refused change is a ValidationException carrying the message to show, as in RadioService.
 */
class RadioOpnames
{
    public function __construct(private RadioBulk $bulk, private RadioService $radios, private ServiceTickets $tickets)
    {
    }

    /** The radios that should be found there: active, of the department (and company), not at a service center or lost. */
    public function expected(int $departmentId, ?int $companyId): Builder
    {
        return Radio::query()->active()->where('department_id', $departmentId)
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->whereNotIn('placement', [Placement::Service->value, Placement::Hilang->value]);
    }

    /**
     * What the first screen shows for a department: how many radios are expected there, its last session sent, and the
     * session still open there (to go on with).
     */
    public function outlook(int $departmentId, ?int $companyId): array
    {
        $sessions = fn () => RadioOpnameSession::query()->where('department_id', $departmentId)->where('company_id', $companyId);
        $last = $sessions()->where('status', RadioOpnameSession::SUBMITTED)->latest('submitted_at')->first();
        $open = $sessions()->where('status', RadioOpnameSession::OPEN)->latest('id')->first();

        return [
            'expected' => $this->expected($departmentId, $companyId)->count(),
            'last' => $last === null ? null : ['date' => $last->submitted_at?->toDateString(), 'verified' => (int) ($last->summary['verified'] ?? 0)],
            'open' => $open?->id,
        ];
    }

    /**
     * The sessions to show on the first screen: the open ones (anybody may go on with one), and the latest sent.
     *
     * @return array{open: list<array>, recent: list<array>}
     */
    public function overview(): array
    {
        $line = fn (RadioOpnameSession $session) => [
            'id' => $session->id,
            'place' => $session->place(),
            'date' => ($session->submitted_at ?? $session->created_at)?->toDateString(),
            'verified' => $session->isOpen() ? $session->items()->count() : (int) ($session->summary['verified'] ?? 0),
            'expected' => $session->expected,
        ];

        return [
            'open' => RadioOpnameSession::query()->where('status', RadioOpnameSession::OPEN)->latest('id')->limit(10)->get()->map($line)->values()->all(),
            'recent' => RadioOpnameSession::query()->where('status', RadioOpnameSession::SUBMITTED)->latest('submitted_at')->limit(5)->get()->map($line)->values()->all(),
        ];
    }

    /** Starts a session for a department (and company); there being one open there already, that one goes on. */
    public function start(int $departmentId, ?int $companyId, User $user): RadioOpnameSession
    {
        $open = RadioOpnameSession::query()->where('status', RadioOpnameSession::OPEN)
            ->where('department_id', $departmentId)->where('company_id', $companyId)->latest('id')->first();
        if ($open !== null) {
            return $open;
        }
        $department = RadioDepartment::query()->find($departmentId) ?? $this->refuse('Department itu tidak ada di master data.');
        $company = $companyId === null ? null : (RadioCompany::query()->find($companyId) ?? $this->refuse('Company itu tidak ada di master data.'));

        return RadioOpnameSession::create([
            'department_id' => $department->id,
            'department_name' => $department->name,
            'company_id' => $company?->id,
            'company_name' => $company?->code ?: $company?->name,
            'status' => RadioOpnameSession::OPEN,
            'expected' => $this->expected($department->id, $company?->id)->count(),
            'started_by' => $user->id,
        ]);
    }

    /** A radio by what was typed or scanned: its Radio ID, else its SN (an archived one too, to say so). */
    public function find(string $typed): ?Radio
    {
        $typed = trim($typed);
        if ($typed === '') {
            return null;
        }
        $radio = null;
        if (ctype_digit($typed) && strlen($typed) <= 9) {
            $radio = Radio::query()->active()->where('dmr_id', (int) $typed)->first();
        }

        return $radio ?? Radio::query()->where('serial_number', RadioConflicts::normalizeSerial($typed))->orderBy('archived_at')->first();
    }

    /** A radio as the screen of its check shows it, with what this session knows of it. */
    public function card(RadioOpnameSession $session, Radio $radio): array
    {
        $radio->loadMissing(['radioType', 'department', 'company']);
        $item = RadioOpnameItem::query()->where('session_id', $session->id)->where('radio_id', $radio->id)->first();

        return [
            'id' => $radio->id,
            'dmr_id' => $radio->dmr_id,
            'mdc' => $radio->mdc_code,
            'sn' => $radio->serial_number,
            'type' => implode(' · ', array_filter([$radio->radioType?->name, $radio->radioType?->category], 'filled')),
            'user' => $radio->user_name,
            'department' => $radio->department?->name,
            'last_verified' => $radio->last_verified_at?->toDateString(),
            'condition' => $radio->condition?->value,
            'placement' => $radio->placement?->label(),
            'archived' => $radio->isArchived(),
            // Registered where this session counts.
            'here' => (int) $radio->department_id === $session->department_id
                && ($session->company_id === null || (int) $radio->company_id === $session->company_id),
            'scanned' => $item === null ? null : [
                'holder' => $item->holder,
                'holder_name' => $item->holder_name,
                'condition' => $item->condition,
                'note' => $item->note,
                'ticket' => $item->ticket_number,
            ],
        ];
    }

    /**
     * Saves what was found of a radio: who has it (register, other with a name, none) and its condition. Scanning it
     * again changes what was saved.
     *
     * @param array{holder: string, holder_name?: ?string, condition: string, note?: ?string} $data
     */
    public function scan(RadioOpnameSession $session, Radio $radio, array $data, User $user): RadioOpnameItem
    {
        $this->mustBeOpen($session);
        if ($radio->trashed()) {
            $this->refuse('Radio ini sudah dihapus dari register.');
        }
        if ($radio->isArchived()) {
            $this->refuse('Radio ini sudah diarsipkan: tidak bisa diverifikasi. Pulihkan dulu di Register Radio.');
        }
        $other = $data['holder'] === 'other';
        $name = trim((string) ($data['holder_name'] ?? ''));
        if ($other && $name === '') {
            $this->refuse('Tulis nama yang memegang radionya.');
        }

        $item = RadioOpnameItem::query()->updateOrCreate(['session_id' => $session->id, 'radio_id' => $radio->id], [
            'radio_label' => $radio->display_name,
            'holder' => $data['holder'],
            'holder_name' => $other ? $name : null,
            'condition' => $data['condition'],
            'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
            'scanned_by' => $user->id,
        ]);
        $session->touch();

        return $item;
    }

    /** A problem of the radio, found in the session: the scan is saved, and a service ticket opened with the note. */
    public function report(RadioOpnameSession $session, Radio $radio, array $data, User $user): ServiceTicket
    {
        if (blank($data['note'] ?? null)) {
            $this->refuse('Tulis masalahnya di catatan dulu.');
        }
        $item = $this->scan($session, $radio, $data, $user);
        $ticket = $this->tickets->open([
            'radio_id' => $radio->id,
            'issue' => mb_substr(trim((string) $data['note']), 0, 500),
            'reported_by' => mb_substr('Stock opname · ' . $session->department_name, 0, 150),
        ], $user);
        $item->forceFill(['ticket_number' => $ticket->number])->save();

        return $ticket;
    }

    /**
     * The session as the screens show it: the counts, the latest scans, and what needs a decision (the radios expected
     * there that were not found, the ones somebody else has, the ones registered elsewhere).
     */
    public function state(RadioOpnameSession $session): array
    {
        $items = $session->items()->with('radio.department')->orderByDesc('updated_at')->orderByDesc('id')->get();
        $expected = $session->isOpen()
            ? $this->expected($session->department_id, $session->company_id)->with('department')->orderBy('dmr_id')->get()
            : collect();
        $found = $items->pluck('radio_id')->flip();
        $notFound = $session->isOpen() ? $expected->reject(fn (Radio $radio) => $found->has($radio->id))->values() : collect();
        $ids = $expected->pluck('id')->flip();
        $name = fn (?Radio $radio, string $label) => $radio === null ? $label : self::name($radio);

        return [
            'session' => [
                'id' => $session->id,
                'place' => $session->place(),
                'status' => $session->status,
                'date' => ($session->submitted_at ?? $session->created_at)?->toDateString(),
            ],
            'expected' => $session->isOpen() ? $expected->count() : $session->expected,
            'found' => $session->isOpen() ? $items->filter(fn (RadioOpnameItem $item) => $ids->has($item->radio_id))->count() : (int) ($session->summary['verified'] ?? 0),
            'scanned' => $items->count(),
            'recent' => $items->take(5)->map(fn (RadioOpnameItem $item) => [
                'radio' => $name($item->radio, $item->radio_label),
                'who' => self::holderText($item, $item->radio),
                'at' => $item->updated_at?->format('Y-m-d H:i:s'),
            ])->values()->all(),
            'not_found' => $notFound->map(fn (Radio $radio) => [
                'radio' => self::name($radio),
                'who' => implode(', ', array_filter([$radio->user_name, $radio->department?->name], 'filled')),
                'last_verified' => $radio->last_verified_at?->toDateString(),
            ])->all(),
            'other_user' => $items->where('holder', 'other')->map(fn (RadioOpnameItem $item) => [
                'radio' => $name($item->radio, $item->radio_label),
                'register' => (string) $item->radio?->user_name,
                'holder' => (string) $item->holder_name,
            ])->values()->all(),
            'elsewhere' => $items->reject(fn (RadioOpnameItem $item) => $ids->has($item->radio_id) || ! $session->isOpen())
                ->map(fn (RadioOpnameItem $item) => [
                    'radio' => $name($item->radio, $item->radio_label),
                    'department' => (string) $item->radio?->department?->name,
                ])->values()->all(),
            'summary' => $session->summary,
        ];
    }

    /**
     * Sends the session to the register: every radio found is verified today (RadioBulk::verify), its condition put in when
     * it changed; one line in the audit trail. What was not found or is with somebody else is left to a person.
     *
     * @return array{verified: int, not_found: int, other_user: int, skipped: list<array{radio: string, reason: string}>}
     */
    public function submit(RadioOpnameSession $session, User $user): array
    {
        $this->mustBeOpen($session);
        $state = $this->state($session);
        $items = $session->items()->with('radio')->orderBy('id')->get();
        if ($items->isEmpty()) {
            $this->refuse('Belum ada radio yang discan di sesi ini.');
        }

        $today = now()->toDateString();
        $verified = 0;
        $skipped = [];
        foreach ($items as $item) {
            $radio = $item->radio;
            if ($radio === null || $radio->trashed()) {
                $skipped[] = ['radio' => $item->radio_label, 'reason' => 'sudah dihapus dari register'];

                continue;
            }
            if (! $radio->isArchived() && $item->condition !== $radio->condition?->value) {
                try {
                    $this->radios->update($radio, ['condition' => $item->condition], $user->id);
                } catch (ValidationException $e) {
                    $skipped[] = ['radio' => RadioBulk::label($radio), 'reason' => 'kondisi: ' . rtrim((string) collect($e->errors())->flatten()->first(), '.')];
                }
            }
            $result = $this->bulk->verify(collect([$radio]), $today, $this->note($session, $item, $radio), $user->id);
            $verified += $result['done'];
            array_push($skipped, ...$result['skipped']);
        }

        $summary = [
            'verified' => $verified,
            'not_found' => count($state['not_found']),
            'other_user' => count($state['other_user']),
            'skipped' => $skipped,
        ];
        $session->forceFill([
            'status' => RadioOpnameSession::SUBMITTED,
            'expected' => $state['expected'],
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'summary' => $summary,
        ])->save();
        app(AuditLogger::class)->log(AuditLogger::RADIO_OPNAME_SUBMITTED, $session, null, array_filter([
            'Tempat' => $session->place(),
            'Diharapkan' => (string) $state['expected'],
            'Terverifikasi' => (string) $verified,
            'Tidak ditemukan' => (string) $summary['not_found'],
            'Pemegang lain' => (string) $summary['other_user'],
            'Dilewati' => $skipped === [] ? null : collect($skipped)->map(fn ($one) => "{$one['radio']} ({$one['reason']})")->implode('; '),
        ], fn ($value) => $value !== null), $user, 'Stock opname ' . $session->place() . ' · ' . now()->format('d-m-Y'));

        return $summary;
    }

    /** "1042 · MDC 412", or the SN of a radio without an ID. */
    public static function name(Radio $radio): string
    {
        return $radio->dmr_id ? $radio->dmr_id . ' · MDC ' . $radio->mdc_code : ($radio->serial_number ?: '#' . $radio->id);
    }

    /** Who has it, in words: "Dewi Lestari, HSE" (the register's), "Dipegang Sandi Putra", "Tanpa pemegang". */
    private static function holderText(RadioOpnameItem $item, ?Radio $radio): string
    {
        return match ($item->holder) {
            'other' => 'Dipegang ' . $item->holder_name,
            'none' => 'Tanpa pemegang',
            default => implode(', ', array_filter([$radio?->user_name, $radio?->department?->name], 'filled')) ?: 'Sesuai register',
        };
    }

    /** The line of the radio's history: where it was counted, who had it, its condition, the note. */
    private function note(RadioOpnameSession $session, RadioOpnameItem $item, Radio $radio): string
    {
        $holder = match ($item->holder) {
            'other' => "dipegang {$item->holder_name} (register: " . ($radio->user_name ?: '-') . ')',
            'none' => 'tanpa pemegang',
            default => 'dipegang sesuai register',
        };
        $condition = Condition::tryFrom($item->condition)?->label() ?? $item->condition;

        return mb_substr("Stock opname {$session->place()}: {$holder}. Kondisi {$condition}." . ($item->note ? " {$item->note}" : ''), 0, 2000);
    }

    private function mustBeOpen(RadioOpnameSession $session): void
    {
        $session->refresh();
        if (! $session->isOpen()) {
            $this->refuse('Sesi ini sudah dikirim ke register.');
        }
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['opname' => $message]);
    }
}
