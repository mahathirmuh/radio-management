<?php

namespace Modules\RadioManagement\Services;

use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\ServiceTicket;
use Modules\RadioManagement\Models\ServiceTicketEvent;

/**
 * Service tickets (menu "Servis & Perbaikan"): opened for a radio, moved on a step at a time, given notes; and what the
 * page shows of them (the open ones, the four numbers above them).
 *
 * Opening and moving run one at a time on the register (RegisterLock): the number of a new ticket is the next one, a radio
 * has one open ticket at most, and a step that moves the radio (sent, back) changes the radio and writes its history in
 * the same turn. A refused write is a ValidationException carrying the message to show (in the language of the page), as
 * in RadioService.
 *
 * What a ticket writes down (its activity, the radio's history, the audit trail) is in Indonesian, whatever the language
 * of the page: a step in the activity in the forms of RadioManagement::service.events, which eventText() reads back to
 * show it in another language.
 */
class ServiceTickets
{
    /** The language what is written down is kept in. */
    private const KEPT = 'id';

    /** The forms a step is written down in (RadioManagement::service.events), each step's longer one first. */
    private const EVENT_FORMS = [
        ServiceTicket::REPORTED => ['reported'],
        ServiceTicket::SENT => ['sent_ba', 'sent'],
        ServiceTicket::IN_REPAIR => ['in_repair_quote', 'in_repair'],
        ServiceTicket::READY => ['ready_cost', 'ready'],
        ServiceTicket::RETURNED => ['returned'],
    ];

    public function __construct(private RegisterLock $lock, private RadioService $radios)
    {
    }

    /**
     * @param array{radio_id: int, issue: string, reported_by?: ?string} $data
     */
    public function open(array $data, ?User $user): ServiceTicket
    {
        return $this->lock->run(function () use ($data, $user) {
            $radio = Radio::query()->with(['radioType', 'department'])->find($data['radio_id']);
            if ($radio === null) {
                $this->refuse(__('RadioManagement::service.refusals.not_in_register'));
            }
            if ($radio->isArchived()) {
                $this->refuse(__('RadioManagement::service.refusals.archived'));
            }
            $open = ServiceTicket::query()->where('radio_id', $radio->id)->whereIn('status', ServiceTicket::OPEN)->value('number');
            if ($open !== null) {
                $this->refuse(__('RadioManagement::service.refusals.open', ['number' => $open]));
            }

            $ticket = ServiceTicket::create([
                'number' => $this->nextNumber(),
                'radio_id' => $radio->id,
                'radio_label' => $radio->display_name,
                'radio_dmr_id' => $radio->dmr_id,
                'radio_sn' => $radio->serial_number,
                'radio_type' => $radio->radioType?->name,
                'status' => ServiceTicket::REPORTED,
                'issue' => trim((string) $data['issue']),
                'reported_by' => self::text($data['reported_by'] ?? null) ?? self::holder($radio),
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ]);
            $this->event($ticket, ServiceTicketEvent::STATUS, self::written('reported', ['issue' => $ticket->issue]), $user, ServiceTicket::REPORTED);
            $this->audit(AuditLogger::SERVICE_CREATED, $ticket, null, [
                'Radio' => $ticket->radio_label,
                'Masalah' => $ticket->issue,
                'Dilaporkan oleh' => $ticket->reported_by,
            ], $user);

            return $ticket;
        });
    }

    /**
     * Moves the ticket on to its next step, with what that step asks for:
     *  - sent: the service center, the day it went (today when not given), the BA, the day it should be back, the quote.
     *    The radio is at "Service" from then on, with a line "Service" in its history;
     *  - in_repair: the quote and the day it should be back, when they are known now;
     *  - ready: the cost;
     *  - returned: the day it came back (today when not given), where the radio goes and in what condition, the cost. The
     *    radio is put there, with a line "Service" in its history.
     * The request checks the values (ServiceTicketController); this checks that the step is still the next one.
     *
     * @param array<string, mixed> $data
     */
    public function move(ServiceTicket $ticket, string $status, array $data, ?User $user): ServiceTicket
    {
        return $this->lock->run(function () use ($ticket, $status, $data, $user) {
            $ticket->refresh();
            $next = $ticket->nextStatus();
            if ($next === null) {
                $this->refuse(__('RadioManagement::service.refusals.finished'));
            }
            if ($status !== $next) {
                $this->refuse(__('RadioManagement::service.refusals.moved'));
            }

            $before = $this->facts($ticket);
            $radio = $ticket->radio_id ? Radio::query()->find($ticket->radio_id) : null;
            $today = now()->toDateString();

            switch ($status) {
                case ServiceTicket::SENT:
                    $ticket->fill([
                        'service_center' => trim((string) $data['service_center']),
                        'sent_at' => self::date($data['sent_at'] ?? null) ?? $today,
                        'document_no' => self::text($data['document_no'] ?? null),
                        'expected_at' => self::date($data['expected_at'] ?? null),
                        'quote' => self::amount($data['quote'] ?? null),
                        'placement_before' => $radio?->placement?->value,
                    ]);
                    $words = $ticket->document_no
                        ? self::written('sent_ba', ['center' => $ticket->service_center, 'ba' => $ticket->document_no])
                        : self::written('sent', ['center' => $ticket->service_center]);
                    if ($radio !== null) {
                        $this->radios->serviceStep($radio, ['placement' => Placement::Service->value],
                            "Dikirim ke {$ticket->service_center} ({$ticket->number}): {$ticket->issue}",
                            $ticket->document_no, $ticket->sent_at->toDateString(), $user?->id);
                    }
                    break;

                case ServiceTicket::IN_REPAIR:
                    $ticket->fill(array_filter([
                        'quote' => self::amount($data['quote'] ?? null),
                        'expected_at' => self::date($data['expected_at'] ?? null),
                    ], fn ($value) => $value !== null));
                    $words = $ticket->quote ? self::written('in_repair_quote', ['quote' => self::rupiah($ticket->quote)]) : self::written('in_repair');
                    break;

                case ServiceTicket::READY:
                    $ticket->cost = self::amount($data['cost'] ?? null) ?? $ticket->cost;
                    $words = $ticket->cost ? self::written('ready_cost', ['cost' => self::rupiah($ticket->cost)]) : self::written('ready');
                    break;

                default: // returned
                    $placement = Placement::from((string) $data['placement']);
                    $condition = Condition::from((string) $data['condition']);
                    $ticket->fill([
                        'returned_at' => self::date($data['returned_at'] ?? null) ?? $today,
                        'cost' => self::amount($data['cost'] ?? null) ?? $ticket->cost,
                    ]);
                    $words = self::written('returned', ['placement' => $placement->label(), 'condition' => $condition->label()]);
                    if ($radio !== null) {
                        $this->radios->serviceStep($radio, ['placement' => $placement->value, 'condition' => $condition->value],
                            "Kembali dari {$ticket->service_center} ({$ticket->number}). Kondisi: {$condition->label()}.",
                            $ticket->document_no, $ticket->returned_at->toDateString(), $user?->id);
                    }
            }

            $ticket->status = $status;
            $ticket->updated_by = $user?->id;
            $ticket->save();
            $this->event($ticket, ServiceTicketEvent::STATUS, $words, $user, $status);
            [$old, $new] = AuditLogger::diff($before, $this->facts($ticket));
            $this->audit(AuditLogger::SERVICE_STATUS_CHANGED, $ticket, $old, $new, $user);

            return $ticket;
        });
    }

    /** A note on the ticket, in its activity (any step, also once the radio is back). */
    public function note(ServiceTicket $ticket, string $note, ?User $user): ServiceTicketEvent
    {
        return DB::transaction(function () use ($ticket, $note, $user) {
            $event = $this->event($ticket, ServiceTicketEvent::NOTE, trim($note), $user);
            $ticket->forceFill(['updated_by' => $user?->id])->touch();
            $this->audit(AuditLogger::SERVICE_NOTE_ADDED, $ticket, null, ['Catatan' => trim($note)], $user);

            return $event;
        });
    }

    /**
     * The four numbers above the board: the open tickets (reported, at a service center), the average days away of the
     * radios that came back in the last 90 days, how many are overdue, and the tickets closed this quarter with their cost.
     *
     * @return array{open: int, reported: int, away: int, overdue: int, turnaround: ?int, closed: int, cost: int}
     */
    public function stats(?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $open = ServiceTicket::query()->whereIn('status', ServiceTicket::OPEN)->get(['id', 'status', 'sent_at', 'created_at']);
        $back = ServiceTicket::query()->where('status', ServiceTicket::RETURNED)->whereNotNull('sent_at')
            ->where('returned_at', '>=', $today->copy()->subDays(90)->toDateString())->get(['id', 'sent_at', 'returned_at', 'created_at']);
        $quarter = fn () => ServiceTicket::query()->where('status', ServiceTicket::RETURNED)
            ->where('returned_at', '>=', $today->copy()->firstOfQuarter()->toDateString());

        return [
            'open' => $open->count(),
            'reported' => $open->where('status', ServiceTicket::REPORTED)->count(),
            'away' => $open->whereIn('status', ServiceTicket::AWAY)->count(),
            'overdue' => $open->filter(fn (ServiceTicket $ticket) => $ticket->isOverdue($today))->count(),
            'turnaround' => $back->isEmpty() ? null : (int) round($back->avg(fn (ServiceTicket $ticket) => $ticket->daysAway())),
            'closed' => $quarter()->count(),
            'cost' => (int) $quarter()->sum('cost'),
        ];
    }

    /** The open tickets, the newest first (each column of the board keeps that order). */
    public function openTickets(): Collection
    {
        return ServiceTicket::query()->whereIn('status', ServiceTicket::OPEN)->orderByDesc('id')->limit(1000)->get();
    }

    /** Every ticket, the newest first, that holds what was typed and went to that service center (the export). */
    public function search(?string $typed, ?string $center): Builder
    {
        $query = ServiceTicket::query()->orderByDesc('id');
        $typed = trim((string) $typed);
        if ($typed !== '') {
            $query->where(function (Builder $q) use ($typed) {
                Like::any($q, ['number', 'radio_label', 'radio_sn', 'radio_type', 'issue', 'reported_by', 'service_center'], $typed);
                if (ctype_digit($typed) && strlen($typed) <= 9) {
                    $q->orWhere('radio_dmr_id', (int) $typed);
                }
            });
        }
        if (filled($center)) {
            $query->where('service_center', $center);
        }

        return $query;
    }

    /** The service centers written on tickets so far: the filter, and what the field offers (K13d). */
    public function centers(): array
    {
        return ServiceTicket::query()->whereNotNull('service_center')->where('service_center', '<>', '')
            ->distinct()->orderBy('service_center')->limit(200)->pluck('service_center')->all();
    }

    /** Who has the radio: "Siti Rahma · Laboratory", "LV 12 · Light vehicle". */
    public static function holder(Radio $radio): ?string
    {
        $parts = array_filter([trim((string) $radio->user_name), trim((string) $radio->department?->name)], 'filled');

        return $parts ? mb_substr(implode(' · ', $parts), 0, 150) : null;
    }

    /** "Rp 1.850.000". */
    public static function rupiah(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /**
     * A line of the activity of a ticket as the page shows it, in its language (or in $locale). A note shows as it was
     * typed. A step shows in the words of that language, with what was written into it (the service center, the BA, an
     * amount) as it was written, and a location or a condition by its name in that language; a line that is in none of
     * the forms (written before them) shows as it was written.
     */
    public static function eventText(ServiceTicketEvent $event, ?string $locale = null): string
    {
        $note = (string) $event->note;
        $locale ??= app()->getLocale();
        if ($event->kind !== ServiceTicketEvent::STATUS || $locale === self::KEPT) {
            return $note;
        }
        foreach (self::EVENT_FORMS[$event->status] ?? [] as $form) {
            $values = self::read(self::written($form), $note);
            if ($values === null) {
                continue;
            }
            if (isset($values['placement'])) {
                $values['placement'] = self::named(Placement::cases(), $values['placement'], $locale);
            }
            if (isset($values['condition'])) {
                $values['condition'] = self::named(Condition::cases(), $values['condition'], $locale);
            }

            return __('RadioManagement::service.events.' . $form, $values, $locale);
        }

        return $note;
    }

    /**
     * A location or a condition written down by the name kept (label()), by its name in $locale; another name stays.
     *
     * @param array<int, Placement|Condition> $cases
     */
    private static function named(array $cases, string $kept, string $locale): string
    {
        foreach ($cases as $case) {
            if ($case->label() === $kept) {
                return $case->display($locale);
            }
        }

        return $kept;
    }

    /** The ticket as the audit trail shows a change of it. */
    private function facts(ServiceTicket $ticket): array
    {
        return [
            'Status' => $ticket->label(),
            'Pusat servis' => $ticket->service_center,
            'Tanggal kirim' => $ticket->sent_at?->format('d-m-Y'),
            'No. BA' => $ticket->document_no,
            'Perkiraan kembali' => $ticket->expected_at?->format('d-m-Y'),
            'Penawaran' => $ticket->quote ? self::rupiah($ticket->quote) : null,
            'Biaya' => $ticket->cost ? self::rupiah($ticket->cost) : null,
            'Tanggal kembali' => $ticket->returned_at?->format('d-m-Y'),
        ];
    }

    /** SRV-0001, then one more than the last one given (under the lock, so never twice). */
    private function nextNumber(): string
    {
        $last = ServiceTicket::query()->orderByDesc('id')->value('number');
        $number = $last !== null && preg_match('/^SRV-(\d+)$/', $last, $match) ? (int) $match[1] : 0;

        do {
            $candidate = sprintf('SRV-%04d', ++$number);
        } while (ServiceTicket::query()->where('number', $candidate)->exists());

        return $candidate;
    }

    /** A step in the activity as it is written down: $form of RadioManagement::service.events, in Indonesian. */
    private static function written(string $form, array $values = []): string
    {
        return __('RadioManagement::service.events.' . $form, $values, self::KEPT);
    }

    /** What $line holds in the places of the :names of $form, or null when it is not written in that form. */
    private static function read(string $form, string $line): ?array
    {
        $pattern = preg_replace('/\\\\:([a-z_]+)/', '(?<$1>.+?)', preg_quote($form, '/'));
        if (! preg_match('/^' . $pattern . '\z/su', $line, $found)) {
            return null;
        }

        return array_filter($found, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function event(ServiceTicket $ticket, string $kind, string $note, ?User $user, ?string $status = null): ServiceTicketEvent
    {
        return ServiceTicketEvent::create([
            'service_ticket_id' => $ticket->id,
            'kind' => $kind,
            'status' => $status,
            'note' => mb_substr($note, 0, 1000),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
        ]);
    }

    private function audit(string $action, ServiceTicket $ticket, ?array $old, ?array $new, ?User $user): void
    {
        app(AuditLogger::class)->log($action, $ticket, $old, $new, $user, $ticket->auditLabel());
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['ticket' => $message]);
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function date(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : Carbon::parse($text)->toDateString();
    }

    private static function amount(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}
