<?php

namespace Modules\RadioManagement\Models;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The signature of one person at ICT (menu "E-Sign Master"): a picture that belongs to one account and is put into the
 * box "PROCESSED BY" of the IT Equipment Checkout Form when that account opens the form.
 *
 * The picture is kept encrypted (the application's key) and is never written to a file.
 *
 * @property int $id
 * @property int $user_id
 * @property string $display_name
 * @property string $image the PNG as a data: address
 * @property bool $active
 */
class ItSignature extends Model
{
    protected $table = 'it_signatures';

    protected $guarded = [];

    protected $hidden = ['image'];

    protected $casts = [
        'user_id' => 'integer',
        'active' => 'boolean',
        'image' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function uses(): HasMany
    {
        return $this->hasMany(ItSignatureUse::class, 'signature_id');
    }

    /**
     * Whether the tables are there. The code of this folder is live as soon as it is saved, the migration is run by
     * hand: until then the form is printed without a signature, instead of failing. (Asked once a minute.)
     */
    public static function ready(): bool
    {
        return Cache::remember('it-signatures-table', 60, fn () => Schema::hasTable('it_signatures') && Schema::hasTable('it_signature_uses'));
    }

    /** The signature that goes on a form this account opens: its own, when it has one that is in use. */
    public static function of(?User $user): ?self
    {
        if ($user === null || ! self::ready()) {
            return null;
        }

        return self::query()->where('user_id', $user->id)->where('active', true)->first();
    }
}
