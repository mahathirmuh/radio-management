<?php

namespace App\Services\Notifications;

use App\Models\Notification\UserNotification;
use App\Models\UserManagement\User;
use App\Services\Language;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\SharePointDocs\Services\MenuAccess;
use Throwable;

/**
 * The notifications under the bell of the header: written here, and only here.
 *
 * A notification is never an obstacle: one that cannot be written (the table is not there yet, the database says no) is
 * left out with the reason in the log, and whatever was being done goes on. Only people get them: an API client never
 * signs in, an inactive account cannot, a deleted one is gone.
 */
class Notifier
{
    /**
     * Gives one notification to each of $users, written in the language of each of them: a title or a text given as
     * fn (string $locale) => string is asked once per person (__('...', [...], $locale)).
     *
     * @param  iterable<User>|User  $users
     * @param  string|Closure(string): string  $title
     * @param  string|Closure(string): string|null  $body
     * @param  ?string  $url  a path of this application ("/documents/edit-approvals?status=pending"); anything else is dropped
     * @param  ?string  $key  the same key is never given to the same person twice (a periodic notice, "token-expiring:12");
     *     without one, every call is a notification of its own
     * @return int how many were written
     */
    public function send(iterable|User $users, string $kind, string|Closure $title, string|Closure|null $body = null, ?string $url = null, ?string $key = null): int
    {
        if (! UserNotification::ready()) {
            return 0;
        }

        $written = 0;
        foreach ($users instanceof User ? [$users] : $users as $user) {
            if (! self::receives($user)) {
                continue;
            }
            try {
                if ($key !== null && UserNotification::query()->of($user)->where('dedupe_key', $key)->exists()) {
                    continue;
                }
                $locale = Language::of($user);
                $text = $body instanceof Closure ? $body($locale) : $body;
                UserNotification::create([
                    'user_id' => $user->id,
                    'kind' => $kind,
                    'title' => Str::limit($title instanceof Closure ? $title($locale) : $title, 250, '…'),
                    'body' => $text === null ? null : Str::limit($text, 990, '…'),
                    'url' => self::path($url),
                    'dedupe_key' => $key === null ? 'once:' . Str::uuid() : mb_substr($key, 0, 150),
                ]);
                $written++;
            } catch (UniqueConstraintViolationException) {
                // Given at the same moment by another request (or by the other installation on the same database).
            } catch (Throwable $e) {
                Log::warning('A notification could not be written.', ['kind' => $kind, 'user' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        return $written;
    }

    /**
     * The people who may use the page of $routeName with $permission, as Role & Permission says (Super Admin always):
     * who is told about something to be done there.
     *
     * @param 'Read'|'Create'|'Update'|'Delete' $permission
     * @return Collection<int, User>
     */
    public static function whoMay(string $routeName, string $permission = 'Read'): Collection
    {
        return User::query()->people()->with('roles')->orderBy('id')->get()
            ->filter(fn (User $user) => self::receives($user) && MenuAccess::allows($user, $routeName, $permission))
            ->values();
    }

    /** Whether $user gets notifications at all: a person whose account is active. */
    public static function receives(mixed $user): bool
    {
        return $user instanceof User && ! $user->trashed() && $user->isActive() && ! $user->isApiClient();
    }

    /** Only a path of this application is kept as a link: another site ("//host", "https://...", "javascript:") is none. */
    public static function path(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $url = mb_substr($url, 0, 500);

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\') ? $url : null;
    }
}
