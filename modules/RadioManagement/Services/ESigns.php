<?php

namespace Modules\RadioManagement\Services;

use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\RadioManagement\Models\ItSignature;
use Modules\RadioManagement\Models\ItSignatureUse;

/**
 * The signatures of "E-Sign Master" as the page and the API look them up, and what the audit trail says of them. Never
 * the picture: it leaves the application only on the checkout form that its own account opens.
 */
class ESigns
{
    /** The signatures whose printed name, or whose account's name or e-mail, holds what is searched for; by printed name. */
    public function search(string $search): Builder
    {
        $search = trim($search);

        return ItSignature::query()
            ->with('user:id,name,email,deleted_at')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                Like::any($q, ['display_name'], $search);
                $q->orWhereIn('user_id', User::withTrashed()->where(fn ($u) => Like::any($u, ['name', 'email'], $search))->select('id'));
            }))
            ->orderBy('display_name');
    }

    /**
     * How often each signature was put on a form, and when last.
     *
     * @param array<int, int> $ids
     * @return Collection<int, object{signature_id: int, total: int, last_at: string}> by signature id
     */
    public function useCounts(array $ids): Collection
    {
        return ItSignatureUse::query()->whereIn('signature_id', $ids)
            ->selectRaw('signature_id, count(*) as total, max(created_at) as last_at')->groupBy('signature_id')->get()->keyBy('signature_id');
    }

    /**
     * Switches a signature off: forms opened by its account get an empty box again. Switching it back on is done on the page.
     *
     * @return bool whether it was on
     */
    public function deactivate(ItSignature $signature, ?User $by): bool
    {
        if (! $signature->active) {
            return false;
        }

        $owner = User::withTrashed()->find($signature->user_id);
        $before = $this->facts($signature, $owner);
        $signature->update(['active' => false, 'updated_by' => $by?->id]);
        app(AuditLogger::class)->logChanges(AuditLogger::ESIGN_UPDATED, $signature, $before, $this->facts($signature, $owner), $owner?->email);

        return true;
    }

    /**
     * A signature as the audit trail shows it: whose it is and the name printed under it. Never the picture.
     *
     * @return array<string, string|null>
     */
    public function facts(ItSignature $signature, ?User $owner): array
    {
        return [
            'Account' => $owner?->email,
            'Name printed' => $signature->display_name,
            'Active' => $signature->active ? 'Yes' : 'No',
        ];
    }
}
