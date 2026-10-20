<?php

namespace App\Services\Audit;

use App\Models\Log\AuditLog;
use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

/**
 * Writes the audit trail (menu "Audit Log"): sign-ins, failed sign-ins and sign-outs, and every change made in the
 * application, after it has been made, with the values before and after.
 *
 * Every value passes through redact(): a password, a token or a picture of a signature never reaches the table,
 * which is read by people other than the ones it is about. Writing an entry never stops the action it records: a
 * failure goes to the application log and the action goes on. Before the migration of the table has run, nothing is
 * written.
 */
class AuditLogger
{
    /* Actions. Kept as keys: the page filters on them, and they are what is searched for. */

    public const LOGIN = 'auth.login';

    public const LOGIN_FAILED = 'auth.login_failed';

    public const LOGOUT = 'auth.logout';

    /** Signed out by the server: idle too long, or the maximum length of a session reached (EndExpiredSession). */
    public const SESSION_EXPIRED = 'auth.session_expired';

    public const USER_CREATED = 'user.created';

    public const USER_UPDATED = 'user.updated';

    public const USER_STATUS_CHANGED = 'user.status_changed';

    /** The password itself is never recorded: only that one was set. */
    public const USER_PASSWORD_RESET = 'user.password_reset';

    public const USER_DELETED = 'user.deleted';

    public const ROLE_CREATED = 'role.created';

    public const ROLE_UPDATED = 'role.updated';

    public const ROLE_DELETED = 'role.deleted';

    public const RADIO_CREATED = 'radio.created';

    public const RADIO_UPDATED = 'radio.updated';

    public const RADIO_ARCHIVED = 'radio.archived';

    public const RADIO_RESTORED = 'radio.restored';

    public const RADIO_DELETED = 'radio.deleted';

    public const RADIO_HISTORY_ADDED = 'radio.history_added';

    /** A radio handed over to someone (the dialog "Serah terima"): the receiver's signature is never part of it. */
    public const RADIO_HANDED_OVER = 'radio.handed_over';

    public const RADIO_IMPORTED = 'radio.imported';

    /** Servis & Perbaikan: a ticket opened for a radio, moved a step on (sent, in repair, ready, back), given a note. */
    public const SERVICE_CREATED = 'service.created';

    public const SERVICE_STATUS_CHANGED = 'service.status_changed';

    public const SERVICE_NOTE_ADDED = 'service.note_added';

    /** Perlu Perhatian: an item of the register's checks given to somebody, or set aside with a reason. */
    public const ATTENTION_ASSIGNED = 'attention.assigned';

    public const ATTENTION_DISMISSED = 'attention.dismissed';

    /** Tipe Radio, Perusahaan, Department, Template Channel: the entry names which. */
    public const MASTER_CREATED = 'master_data.created';

    public const MASTER_UPDATED = 'master_data.updated';

    public const MASTER_DELETED = 'master_data.deleted';

    public const ESIGN_CREATED = 'esign.created';

    public const ESIGN_UPDATED = 'esign.updated';

    public const ESIGN_DELETED = 'esign.deleted';

    /** A checkout form was opened with the signature of the account that opened it. */
    public const ESIGN_USED = 'esign.used';

    public const EDIT_REQUESTED = 'document.edit_requested';

    public const EDIT_APPROVED = 'document.edit_approved';

    public const EDIT_REJECTED = 'document.edit_rejected';

    /** The accounts of applications and the tokens of the API (page API Tokens). Never the token itself. */
    public const API_CLIENT_CREATED = 'api.client_created';

    public const API_CLIENT_UPDATED = 'api.client_updated';

    public const API_CLIENT_DELETED = 'api.client_deleted';

    public const API_TOKEN_CREATED = 'api.token_created';

    public const API_TOKEN_REVOKED = 'api.token_revoked';

    /** The user agent of an entry written by an artisan command. */
    public const CONSOLE = 'console';

    /**
     * Names of fields whose value is replaced by REDACTED: matched as parts of the name, in any case, so "Password",
     * "remember_token" and "client_secret" are all caught.
     */
    private const REDACTED_KEYS = [
        'password', 'secret', 'token', 'credential', 'certificate', 'api_key', 'apikey', 'private_key',
        'authorization', 'image',
    ];

    public const REDACTED = '[redacted]';

    /** A value longer than this is cut: the trail says what changed, it is no copy of the data. */
    private const MAX_VALUE = 500;

    /**
     * Records an action. $old and $new name fields the way the page that made the change does ("Status", "Radio ID").
     *
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     * @param User|null $user who did it; by default whoever is signed in
     * @param string|null $label what the record is, in words that stay readable after it is gone ("ID 4021 · SN 867TZB5309")
     */
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $old = null,
        ?array $new = null,
        ?User $user = null,
        ?string $label = null,
    ): ?AuditLog {
        try {
            if (! AuditLog::ready()) {
                return null;
            }
            // A record as it was made, or as it was when it went: only the fields it had. (Next to a value on the other
            // side, an empty one does say something: "Radio ID: 2001 → -".)
            $filled = fn (array $values) => array_filter($values, fn ($value) => $value !== null && $value !== '');
            if (! $old && $new) {
                $new = $filled($new);
            } elseif ($old && ! $new) {
                $old = $filled($old);
            }
            $actor = $user ?? Auth::user();

            return AuditLog::create([
                'user_id' => $actor?->getKey(),
                'user_email' => $actor === null ? null : mb_substr((string) $actor->email, 0, 255),
                'action' => $action,
                'entity_type' => $entity === null ? null : class_basename($entity),
                'entity_id' => $entity?->getKey(),
                'entity_label' => blank($label) ? null : mb_substr((string) $label, 0, 255),
                'old_values' => $old ? $this->redact($old) : null,
                'new_values' => $new ? $this->redact($new) : null,
                'ip_address' => $this->console() ? null : Request::ip(),
                'user_agent' => $this->console() ? self::CONSOLE : mb_substr((string) Request::userAgent(), 0, 512),
            ] + (AuditLog::viaReady() ? ['via' => $this->via($actor)] : []));
        } catch (Throwable $e) {
            // The action itself has been done: an entry that could not be written must not undo it, or answer an error.
            report($e);

            return null;
        }
    }

    /**
     * Records a change: only the fields whose value moved between $before and $after. Nothing is written when none did
     * (a form saved as it was).
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public function logChanges(string $action, ?Model $entity, array $before, array $after, ?string $label = null): ?AuditLog
    {
        [$old, $new] = self::diff($before, $after);

        return $new === [] && $old === [] ? null : $this->log($action, $entity, $old, $new, label: $label);
    }

    /**
     * The fields of $after whose value differs from $before, as [old values, new values]. A field that is only in
     * $before is taken to be empty now.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $old = [];
        $new = [];
        foreach ($after + array_fill_keys(array_keys(array_diff_key($before, $after)), null) as $field => $value) {
            $was = $before[$field] ?? null;
            if (AuditLog::shown($was) === AuditLog::shown($value)) {
                continue;
            }
            $old[$field] = $was;
            $new[$field] = $value;
        }

        return [$old, $new];
    }

    /**
     * Replaces the values of sensitive fields, walking nested lists, and cuts long ones. A picture sent as a data:
     * address is never kept, whatever the field is called.
     *
     * @param array<string|int, mixed> $values
     * @return array<string|int, mixed>
     */
    public function redact(array $values): array
    {
        $kept = [];
        foreach ($values as $key => $value) {
            $kept[$key] = match (true) {
                $this->sensitive((string) $key) => $value === null ? null : self::REDACTED,
                is_array($value) => $this->redact($value),
                is_string($value) && str_starts_with(ltrim($value), 'data:') => self::REDACTED,
                is_string($value) && mb_strlen($value) > self::MAX_VALUE => mb_substr($value, 0, self::MAX_VALUE) . '…',
                is_scalar($value) || $value === null => $value,
                default => (string) json_encode($value),
            };
        }

        return $kept;
    }

    private function sensitive(string $key): bool
    {
        $key = mb_strtolower($key);
        foreach (self::REDACTED_KEYS as $part) {
            if (str_contains($key, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * How the action came in: through a token of the API ("API · Power BI (#3)"), or the pages (nothing). The token is
     * named by its name and number, never by itself.
     */
    private function via(?User $actor): ?string
    {
        $token = $actor?->currentAccessToken();

        return $token instanceof PersonalAccessToken
            ? mb_substr('API · ' . $token->name . ' (#' . $token->getKey() . ')', 0, 150)
            : null;
    }

    /** An artisan command: there is no visitor, and no address. (The tests run in the console too, with requests.) */
    private function console(): bool
    {
        return app()->runningInConsole() && ! app()->runningUnitTests();
    }
}
