<?php

namespace App\Models\Log;

use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use LogicException;

/**
 * One entry of the audit trail (menu "Audit Log"), written by App\Services\Audit\AuditLogger.
 *
 * Written once and never touched again: there is no updated_at, and changing or deleting an entry is refused. A trail
 * that can be edited from the application is no trail.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** Whether the table is there is asked at most once a minute: the code is live before its migration runs. */
    public const READY_CACHE_KEY = 'audit-logs-table';

    /** The same for the column "via" (a later migration). */
    public const VIA_CACHE_KEY = 'audit-logs-via-column';

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id', 'user_email', 'action', 'entity_type', 'entity_id', 'entity_label',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'via',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('An audit entry is not changed.'));
        static::deleting(fn () => throw new LogicException('An audit entry is not deleted.'));
    }

    /** Whether the table of the trail exists (its migration has run). Until then nothing is written and the page says so. */
    public static function ready(): bool
    {
        return (bool) Cache::remember(self::READY_CACHE_KEY, 60, fn () => Schema::hasTable('audit_logs'));
    }

    /** Whether the column "via" exists (how an entry came in: the pages, or a token of the API). */
    public static function viaReady(): bool
    {
        return (bool) Cache::remember(self::VIA_CACHE_KEY, 60, fn () => Schema::hasColumn('audit_logs', 'via'));
    }

    /** Users are soft-deleted: the entry keeps the name of who did it after the account is removed. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Who did it: the account, else the address it had; nobody signed in is a guest (a failed sign-in), or the system. */
    public function actorName(): string
    {
        return $this->user?->name
            ?? $this->user_email
            ?? ($this->user_agent === AuditLogger::CONSOLE ? 'System' : 'Guest');
    }

    /**
     * What was done, as words that go between the actor and the record: "Budi signed in", "Budi updated radio
     * ID 4021 · SN 867TZB5309". The stored action stays a key (the table shows it, and it is what is searched for).
     * An action without words here shows its key, so a new one is not quietly read as something else.
     */
    public function activityLabel(): string
    {
        return match ($this->action) {
            AuditLogger::LOGIN => 'signed in',
            AuditLogger::LOGIN_FAILED => 'failed to sign in',
            AuditLogger::LOGOUT => 'signed out',
            AuditLogger::SESSION_EXPIRED => 'was signed out: session expired',
            AuditLogger::USER_CREATED => 'created account',
            AuditLogger::USER_UPDATED => 'updated account',
            AuditLogger::USER_STATUS_CHANGED => 'changed the status of',
            AuditLogger::USER_PASSWORD_RESET => 'set the password for',
            AuditLogger::USER_DELETED => 'deleted account',
            AuditLogger::ROLE_CREATED => 'created role',
            AuditLogger::ROLE_UPDATED => 'updated role',
            AuditLogger::ROLE_DELETED => 'deleted role',
            AuditLogger::RADIO_CREATED => 'registered radio',
            AuditLogger::RADIO_UPDATED => 'updated radio',
            AuditLogger::RADIO_ARCHIVED => 'archived radio',
            AuditLogger::RADIO_RESTORED => 'restored radio',
            AuditLogger::RADIO_DELETED => 'deleted radio',
            AuditLogger::RADIO_HISTORY_ADDED => 'added a history entry to radio',
            AuditLogger::RADIO_HANDED_OVER => 'handed over radio',
            AuditLogger::RADIO_IMPORTED => 'imported radios from Excel',
            AuditLogger::MASTER_CREATED => 'added',
            AuditLogger::MASTER_UPDATED => 'updated',
            AuditLogger::MASTER_DELETED => 'deleted',
            AuditLogger::ESIGN_CREATED => 'registered the signature of',
            AuditLogger::ESIGN_UPDATED => 'updated the signature of',
            AuditLogger::ESIGN_DELETED => 'removed the signature of',
            AuditLogger::ESIGN_USED => 'put their signature on the checkout form of',
            AuditLogger::EDIT_REQUESTED => 'asked to change',
            AuditLogger::EDIT_APPROVED => 'approved the change of',
            AuditLogger::EDIT_REJECTED => 'turned down the change of',
            AuditLogger::API_CLIENT_CREATED => 'created API client',
            AuditLogger::API_CLIENT_UPDATED => 'updated API client',
            AuditLogger::API_CLIENT_DELETED => 'deleted API client',
            AuditLogger::API_TOKEN_CREATED => 'created API token',
            AuditLogger::API_TOKEN_REVOKED => 'revoked API token',
            default => (string) $this->action,
        };
    }

    /** "Radio #12", or nothing for an action that is about no record. */
    public function entityName(): ?string
    {
        return $this->entity_type ? $this->entity_type . ' #' . $this->entity_id : null;
    }

    /**
     * The change, one field per line: "Status: Active → Inactive". Values were redacted on the way in, so nothing
     * here can be a secret; a value that is a list is summed up rather than dumped.
     *
     * @return array<int, string>
     */
    public function changeSummary(): array
    {
        $new = is_array($this->new_values) ? $this->new_values : [];
        $old = is_array($this->old_values) ? $this->old_values : [];
        $lines = [];

        foreach ($new + array_diff_key($old, $new) as $field => $ignored) {
            $hadBefore = array_key_exists($field, $old);
            $before = self::shown($old[$field] ?? null);
            $after = array_key_exists($field, $new) ? self::shown($new[$field]) : '-';

            $lines[] = $hadBefore ? sprintf('%s: %s → %s', $field, $before, $after) : sprintf('%s: %s', $field, $after);
        }

        return $lines;
    }

    /** A value as one line of text. */
    public static function shown(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '-',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_scalar($value) => (string) $value,
            default => '…',
        };
    }
}
