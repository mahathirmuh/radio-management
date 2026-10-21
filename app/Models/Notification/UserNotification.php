<?php

namespace App\Models\Notification;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * One notification of one person (the bell in the header). Written by App\Services\Notifications\Notifier only.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property string $title
 * @property ?string $body
 * @property ?string $url
 * @property string $dedupe_key
 * @property ?\Illuminate\Support\Carbon $read_at
 */
class UserNotification extends Model
{
    public const READY_CACHE_KEY = 'user-notifications-table';

    /** A change of a workbook cell waits for this person's approval. */
    public const EDIT_REQUESTED = 'edit_requested';

    /** What became of this person's request: applied, rejected, failed or a conflict. */
    public const EDIT_DECIDED = 'edit_decided';

    /** A token of the API that this person holds, or manages, ends within days. */
    public const TOKEN_EXPIRING = 'token_expiring';

    /** The week's figures of the register that need somebody to act. */
    public const REGISTER_WEEKLY = 'register_weekly';

    /** The tab of the page "Notifikasi" each kind is listed under, and the word beside its title: Radio, Dokumen, Sistem. */
    public const CATEGORIES = [
        self::REGISTER_WEEKLY => 'radio',
        self::EDIT_REQUESTED => 'documents',
        self::EDIT_DECIDED => 'documents',
        self::TOKEN_EXPIRING => 'system',
    ];

    /** The icon of each kind (Tabler icons), as the bell and the page show it. */
    public const ICONS = [
        self::EDIT_REQUESTED => 'ti-file-pencil',
        self::EDIT_DECIDED => 'ti-file-check',
        self::TOKEN_EXPIRING => 'ti-key',
        self::REGISTER_WEEKLY => 'ti-radio',
    ];

    protected $table = 'user_notifications';

    protected $guarded = [];

    protected $casts = [
        'user_id' => 'integer',
        'read_at' => 'datetime',
    ];

    /**
     * Whether the table exists (its migration has run). The code of this folder is live as soon as it is saved, the
     * migration is run by hand: until then the bell stays empty and nothing is written, instead of failing.
     */
    public static function ready(): bool
    {
        return (bool) Cache::remember(self::READY_CACHE_KEY, 60, fn () => Schema::hasTable('user_notifications'));
    }

    public function scopeOf(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function icon(): string
    {
        return self::ICONS[$this->kind] ?? 'ti-bell';
    }

    /** radio, documents or system (a kind added later without a place: system). */
    public function category(): string
    {
        return self::CATEGORIES[$this->kind] ?? 'system';
    }

    /** @return list<string> the kinds listed under $category */
    public static function kindsOf(string $category): array
    {
        return array_keys(array_filter(self::CATEGORIES, fn (string $one) => $one === $category));
    }
}
