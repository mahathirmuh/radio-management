<?php

namespace Modules\RadioManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * A radio that went for repair (menu "Servis & Perbaikan"): reported, sent to a service center, in repair there, ready to
 * be collected, back. Every step, and every note written on it, is a ServiceTicketEvent.
 *
 * The radio is copied into the ticket when it is made (its ID, SN and type): the ticket stays readable when the radio is
 * gone from the register (an import that starts over deletes every radio; it is refused while a ticket is open).
 *
 * @property int $id
 * @property string $number SRV-0001
 * @property ?int $radio_id
 * @property string $radio_label "ID 751 · 903KXM2011"
 * @property ?int $radio_dmr_id
 * @property ?string $radio_sn
 * @property ?string $radio_type
 * @property string $status
 * @property string $issue
 * @property ?string $reported_by
 * @property ?string $service_center
 * @property ?string $document_no the BA it was sent with
 * @property ?Carbon $sent_at
 * @property ?Carbon $expected_at
 * @property ?Carbon $returned_at
 * @property ?int $quote what the service center asked, in rupiah
 * @property ?int $cost what the repair cost, in rupiah
 * @property ?string $placement_before where the radio was before it was sent
 * @property ?Carbon $created_at
 */
class ServiceTicket extends Model
{
    public const REPORTED = 'reported';

    public const SENT = 'sent';

    public const IN_REPAIR = 'in_repair';

    public const READY = 'ready';

    public const RETURNED = 'returned';

    /** The steps, in their order, by the names the audit trail and the export keep (Indonesian); a page shows statusNames(). */
    public const STATUSES = [
        self::REPORTED => 'Dilaporkan',
        self::SENT => 'Dikirim ke servis',
        self::IN_REPAIR => 'Dalam perbaikan',
        self::READY => 'Siap diambil',
        self::RETURNED => 'Kembali',
    ];

    /** A ticket is open until the radio is back. */
    public const OPEN = [self::REPORTED, self::SENT, self::IN_REPAIR, self::READY];

    /** The steps in which the radio is at the service center. */
    public const AWAY = [self::SENT, self::IN_REPAIR, self::READY];

    /** Away for longer than this many days (since it was sent) is overdue. */
    public const OVERDUE_DAYS = 14;

    protected $table = 'service_tickets';

    protected $guarded = [];

    protected $casts = [
        'radio_id' => 'integer',
        'radio_dmr_id' => 'integer',
        'sent_at' => 'date',
        'expected_at' => 'date',
        'returned_at' => 'date',
        'quote' => 'integer',
        'cost' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    /**
     * Whether the tables are there. The code is live as soon as it is saved, the migration is run by hand: until then the
     * page says so, and nothing else changes (an import is not held up by tickets that cannot exist). Asked once a minute.
     */
    public static function ready(): bool
    {
        return Cache::remember('service-tickets-table', 60, fn () => Schema::hasTable('service_tickets') && Schema::hasTable('service_ticket_events'));
    }

    /** How many tickets are open (0 while the tables are not there). */
    public static function openCount(): int
    {
        return self::ready() ? self::query()->whereIn('status', self::OPEN)->count() : 0;
    }

    public function events(): HasMany
    {
        return $this->hasMany(ServiceTicketEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * The steps, in their order, by the names a page shows in its language (or in $locale):
     * RadioManagement::service.statuses. A step without a name there shows the one kept.
     *
     * @return array<string, string>
     */
    public static function statusNames(?string $locale = null): array
    {
        $names = __('RadioManagement::service.statuses', [], $locale);
        $shown = [];
        foreach (self::STATUSES as $status => $kept) {
            $shown[$status] = is_array($names) && isset($names[$status]) ? (string) $names[$status] : $kept;
        }

        return $shown;
    }

    /** The name of its step as the audit trail and the export keep it (Indonesian). */
    public function label(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** The name of its step in the language of the page (or of $locale). */
    public function display(?string $locale = null): string
    {
        return self::statusNames($locale)[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** The step after this one; null once the radio is back. */
    public function nextStatus(): ?string
    {
        $steps = array_keys(self::STATUSES);
        $at = array_search($this->status, $steps, true);

        return $at === false ? null : ($steps[$at + 1] ?? null);
    }

    /** Days away: since it was sent (since it was reported, while it is not sent yet), until it came back. */
    public function daysAway(?Carbon $today = null): int
    {
        $from = ($this->sent_at ?? $this->created_at)?->copy()->startOfDay();
        if ($from === null) {
            return 0;
        }
        $to = ($this->returned_at ?? $today ?? now())->copy()->startOfDay();

        return max(0, (int) round($from->diffInDays($to)));
    }

    /** At the service center for longer than OVERDUE_DAYS. */
    public function isOverdue(?Carbon $today = null): bool
    {
        return in_array($this->status, self::AWAY, true) && $this->daysAway($today) > self::OVERDUE_DAYS;
    }

    /** The radio as a word on a card: its Radio ID, else its SN. */
    public function radioName(): string
    {
        return $this->radio_dmr_id ? (string) $this->radio_dmr_id : ($this->radio_sn ?: $this->radio_label);
    }

    /** How the audit trail names it: "SRV-0412 · ID 751 · 903KXM2011". */
    public function auditLabel(): string
    {
        return $this->number . ' · ' . $this->radio_label;
    }
}
