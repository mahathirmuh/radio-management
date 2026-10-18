<?php

namespace App\Services\Notifications;

use App\Models\Notification\UserNotification;
use App\Models\UserManagement\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Api\Models\ApiToken;
use Modules\RadioManagement\Services\RadioCheckService;
use Modules\SharePointDocs\Services\MenuAccess;
use Throwable;

/**
 * The notices that come from time rather than from an action: a token of the API that ends within days, and the week's
 * figures of the register that need somebody to act. They are made when their person is around (the bell asks for its
 * notifications, and this runs at most once an hour per person), not by a scheduler: this application runs none, and a
 * notification is only seen under the bell anyway. Each has its key (Notifier), so neither a second look nor the other
 * installation on the same database ever gives it twice. Read notifications are cleared after KEEP_READ_DAYS here too.
 */
class NotificationSweep
{
    /** How many days before its end a token is told about. */
    public const TOKEN_NOTICE_DAYS = 7;

    /** Read notifications older than this are removed (days); unread ones stay. */
    public const KEEP_READ_DAYS = 90;

    /** How many checks of the register the weekly notice names (the others are counted). */
    private const WEEKLY_SHOWN = 4;

    public function __construct(private readonly Notifier $notifier)
    {
    }

    /** Makes what is due for $user, at most once an hour unless $force. */
    public function forUser(User $user, bool $force = false): void
    {
        if (! UserNotification::ready() || ! Notifier::receives($user)) {
            return;
        }
        if (! $force && ! Cache::add('notification-sweep:' . $user->id, true, now()->addHour())) {
            return;
        }

        foreach (['expiringTokens', 'weeklyRegister', 'prune'] as $step) {
            try {
                $this->{$step}($user);
            } catch (Throwable $e) {
                Log::warning('A step of the notifications failed.', ['step' => $step, 'user' => $user->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /** The tokens of $user that end within TOKEN_NOTICE_DAYS; for Super Admin, who manages them, those of the API clients too. */
    public function expiringTokens(User $user): void
    {
        if (! ApiToken::ready()) {
            return;
        }
        $type = (new User())->getMorphClass();
        $ending = fn () => ApiToken::query()->where('tokenable_type', $type)->whereNotNull('expires_at')
            ->where('expires_at', '>', now())->where('expires_at', '<=', now()->addDays(self::TOKEN_NOTICE_DAYS));
        $page = route('api-tokens.index', [], false);

        foreach ($ending()->where('tokenable_id', $user->id)->orderBy('expires_at')->get() as $token) {
            $this->notifier->send($user, UserNotification::TOKEN_EXPIRING, 'Token API Anda akan berakhir',
                "Token \"{$token->name}\" berakhir {$this->when($token->expires_at)}. Bila masih dipakai, buat token baru di API Tokens.",
                $page, 'token-expiring:' . $token->id);
        }

        if (! $user->roles->contains('id', 1) || ! User::apiClientReady()) {
            return;
        }
        $clients = User::query()->where('api_client', true)->pluck('name', 'id');
        if ($clients->isEmpty()) {
            return;
        }
        foreach ($ending()->whereIn('tokenable_id', $clients->keys()->all())->orderBy('expires_at')->get() as $token) {
            $this->notifier->send($user, UserNotification::TOKEN_EXPIRING, 'Token API client akan berakhir',
                "Token \"{$token->name}\" milik API client {$clients[$token->tokenable_id]} berakhir {$this->when($token->expires_at)}."
                    . ' Bila aplikasinya masih memakai API, buatkan token baru di API Tokens.',
                $page, 'token-expiring:' . $token->id);
        }
    }

    /**
     * Once a week (ISO week), for whoever may change the register (Update on Register Radio): the checks of the
     * register that have radios to look at, as the dashboard's "Perlu tindakan" shows them. Nothing when nothing is to be done.
     */
    public function weeklyRegister(User $user): void
    {
        if (! MenuAccess::allows($user, 'radio-management.radio.index', 'Update')) {
            return;
        }
        $key = 'register-weekly:' . now()->format('o-\WW');
        if (UserNotification::query()->of($user)->where('dedupe_key', $key)->exists()) {
            return;
        }

        $counts = app(RadioCheckService::class)->summary();
        $issues = collect(RadioCheckService::ISSUES)
            ->map(fn (array $issue, string $code) => $issue['label'] . ' ' . self::number((int) ($counts[$code] ?? 0)))
            ->filter(fn (string $shown, string $code) => (int) ($counts[$code] ?? 0) > 0)
            ->values();
        if ($issues->isEmpty()) {
            return;
        }
        $more = $issues->count() - self::WEEKLY_SHOWN;

        $this->notifier->send($user, UserNotification::REGISTER_WEEKLY, 'Ringkasan register radio minggu ini',
            'Perlu tindakan: ' . $issues->take(self::WEEKLY_SHOWN)->implode(' · ') . ($more > 0 ? " · dan {$more} cek lain" : '') . '.',
            route('dashboard.index', [], false), $key);
    }

    /** Read notifications older than KEEP_READ_DAYS go. */
    public function prune(User $user): void
    {
        UserNotification::query()->of($user)->whereNotNull('read_at')->where('read_at', '<', now()->subDays(self::KEEP_READ_DAYS))->delete();
    }

    /** "hari ini (18-10-2026)", "besok (...)", "dalam 5 hari (...)". */
    private function when(CarbonInterface $end): string
    {
        $days = (int) round(now()->startOfDay()->diffInDays($end->copy()->startOfDay()));
        $date = $end->format('d-m-Y');

        return match (true) {
            $days <= 0 => "hari ini ({$date})",
            $days === 1 => "besok ({$date})",
            default => "dalam {$days} hari ({$date})",
        };
    }

    /** 1011 as the pages write it: 1.011. */
    private static function number(int $count): string
    {
        return number_format($count, 0, ',', '.');
    }
}
