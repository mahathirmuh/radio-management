<?php

namespace App\Services\Audit;

use App\Models\Log\AuditLog;
use App\Models\UserManagement\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Modules\RadioManagement\Services\Like;

/**
 * The entries of the audit trail as the page "Audit Log" and the API find them: newest first.
 */
class AuditLogQuery
{
    /**
     * @param string $search the address the entry was written with, the name of that account now, or what was typed
     *                       into a failed sign-in
     * @param string $action one action key ("user.updated"), or '' for all
     */
    public function build(
        string $search = '',
        string $action = '',
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
        ?string $entityType = null,
        ?int $entityId = null,
    ): Builder {
        return AuditLog::query()
            ->with('user:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($where) use ($search) {
                    // A failed sign-in has no account: what was typed into the form is in its values.
                    Like::any($where, ['user_email'], $search);
                    $where->orWhereIn('user_id', User::withTrashed()->where(fn ($named) => Like::any($named, ['name'], $search))->select('id'))
                        ->orWhere(function ($failed) use ($search) {
                            $failed->where('action', AuditLogger::LOGIN_FAILED)->where(fn ($typed) => Like::any($typed, ['new_values'], $search));
                        });
                });
            })
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            // Whole days: "from" its first moment, "to" up to the next day.
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from->format('Y-m-d') . ' 00:00:00'))
            ->when($to, fn ($query) => $query->where('created_at', '<', (new \DateTimeImmutable($to->format('Y-m-d')))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'))
            ->when($entityType !== null && $entityType !== '', fn ($query) => $query->where('entity_type', $entityType))
            ->when($entityId !== null, fn ($query) => $query->where('entity_id', $entityId))
            ->orderByDesc('created_at')
            // Entries written by one action share their second: without this, rows would repeat across pages, or never show.
            ->orderByDesc('id');
    }
}
