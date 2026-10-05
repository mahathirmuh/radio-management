<?php

namespace Modules\ApprovalManagement\Services;

use App\Models\UserManagement\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\ApprovalManagement\Models\Approval;
use Modules\ApprovalManagement\Models\ApprovalAttachment;
use Modules\ApprovalManagement\Models\ApprovalFieldValue;
use Modules\ApprovalManagement\Models\ApprovalFlow;
use Modules\ApprovalManagement\Models\ApprovalHistory;
use Modules\ApprovalManagement\Models\ApprovalStageInstance;
use Modules\ApprovalManagement\Models\ApprovalStepInstance;
use Modules\ApprovalManagement\Models\Delegation;
use RuntimeException;

/**
 * Dynamic approval engine.
 *
 * Flow = ordered STAGES; each stage owns PARALLEL steps resolved by a rule
 * (ALL / ANY / QUORUM). When an approval starts, the template is snapshotted
 * into *_instances so it can be edited mid-flight (ad-hoc approvers inserted in
 * the middle) without touching the template or other running approvals.
 */
class ApprovalService
{
    /**
     * Start an approval for a model: snapshot the flow into stage/step instances.
     */
    public function createApproval(Model $model, int $flowId): Approval
    {
        return DB::transaction(function () use ($model, $flowId) {
            $flow = ApprovalFlow::with(['stages.steps.fields'])->findOrFail($flowId);

            $approval = Approval::create([
                'approval_flow_id' => $flowId,
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
                'status' => 'PENDING',
            ]);

            $firstStageInstance = null;

            foreach ($flow->stages->sortBy('stage_order')->values() as $index => $stage) {
                $isFirst = $index === 0;

                $stageInstance = ApprovalStageInstance::create([
                    'approval_id' => $approval->id,
                    'stage_id' => $stage->id,
                    'name' => $stage->name,
                    'stage_order' => $stage->stage_order,
                    'rule' => $stage->rule,
                    'quorum_count' => $stage->quorum_count,
                    'status' => $isFirst ? 'ACTIVE' : 'PENDING',
                ]);

                if ($isFirst) {
                    $firstStageInstance = $stageInstance;
                }

                foreach ($stage->steps as $step) {
                    ApprovalStepInstance::create([
                        'approval_id' => $approval->id,
                        'stage_instance_id' => $stageInstance->id,
                        'step_id' => $step->id,
                        'name' => $step->name,
                        'order' => $step->step_order,
                        'user_id' => $step->user_id,
                        'role_id' => $step->role_id,
                        'status' => 'PENDING',
                        'fields_schema' => $this->snapshotFields($step),
                    ]);
                }
            }

            if ($firstStageInstance) {
                $approval->update(['current_stage_instance_id' => $firstStageInstance->id]);
            }

            return $approval->fresh();
        });
    }

    /**
     * Approve the acting user's step. $payload may carry: note, fields, attachments.
     */
    public function approve(Approval $approval, ?string $note = null, array $payload = [], ?User $user = null): Approval
    {
        return $this->act($approval, 'APPROVE', array_merge($payload, ['note' => $note]), $user);
    }

    public function reject(Approval $approval, ?string $note = null, array $payload = [], ?User $user = null): Approval
    {
        return $this->act($approval, 'REJECT', array_merge($payload, ['note' => $note]), $user);
    }

    public function revise(Approval $approval, ?string $note = null, array $payload = [], ?User $user = null): Approval
    {
        return $this->act($approval, 'REVISE', array_merge($payload, ['note' => $note]), $user);
    }

    /**
     * Core action handler: authorize, capture fields, mark the step, advance.
     *
     * @param  array  $payload  ['note' => string, 'fields' => [key=>value],
     *                           'attachments' => [key => [ ['original_name','path','mime','size'], ... ]]]
     */
    public function act(Approval $approval, string $action, array $payload = [], ?User $user = null): Approval
    {
        $user = $user ?? Auth::user();
        $action = strtoupper($action);

        if (! in_array($action, ['APPROVE', 'REJECT', 'REVISE'], true)) {
            throw new RuntimeException("Unsupported approval action: {$action}");
        }

        if ($approval->status !== 'PENDING') {
            throw new RuntimeException('This approval is not currently pending and cannot be acted on.');
        }

        return DB::transaction(function () use ($approval, $action, $payload, $user) {
            $step = $this->resolveActableStep($approval, $user);

            if (! $step) {
                throw new RuntimeException('You are not authorized to act on the current approval step.');
            }

            $note = $payload['note'] ?? null;

            // Required fields/attachments are only enforced on APPROVE.
            if ($action === 'APPROVE') {
                $this->captureFields($approval, $step, $payload, $user);
            }

            if ($action === 'REVISE') {
                // Send back for revision; leave step statuses untouched.
                $this->logHistory($approval, $step, 'REVISE', $note, $user);
                $approval->update(['status' => 'REVISED']);

                return $approval->fresh();
            }

            $step->update([
                'status' => $action === 'APPROVE' ? 'APPROVED' : 'REJECTED',
                'acted_by' => $user?->id,
                'acted_at' => now(),
                'note' => $note,
            ]);

            $this->logHistory($approval, $step, $action, $note, $user);

            if ($action === 'REJECT') {
                $approval->currentStageInstance?->update(['status' => 'REJECTED']);
                $approval->update(['status' => 'REJECTED']);

                return $approval->fresh();
            }

            $this->evaluateStage($approval);

            return $approval->fresh();
        });
    }

    /**
     * Insert an approver at runtime — either as a parallel step in the current
     * stage, or as a brand-new stage slotted right after the current one.
     *
     * @param  array  $data  ['user_id'|'role_id', 'name', 'position', 'rule',
     *                        'quorum_count', 'fields_schema', 'note']
     *                        position: 'into_current_stage' | 'new_stage_after_current'
     */
    public function insertAdhocStep(Approval $approval, array $data, ?User $user = null): ApprovalStepInstance
    {
        $user = $user ?? Auth::user();

        if (empty($data['user_id']) && empty($data['role_id'])) {
            throw new RuntimeException('An ad-hoc approver requires either a user or a role.');
        }

        return DB::transaction(function () use ($approval, $data, $user) {
            $current = $approval->currentStageInstance;

            if (! $current) {
                throw new RuntimeException('There is no active stage to insert an approver into.');
            }

            $position = $data['position'] ?? 'new_stage_after_current';

            if ($position === 'into_current_stage') {
                $stageInstance = $current;
            } else {
                $next = $approval->stageInstances()
                    ->where('stage_order', '>', $current->stage_order)
                    ->orderBy('stage_order')
                    ->first();

                $newOrder = $next
                    ? round(((float) $current->stage_order + (float) $next->stage_order) / 2, 2)
                    : (float) $current->stage_order + 1;

                $stageInstance = ApprovalStageInstance::create([
                    'approval_id' => $approval->id,
                    'stage_id' => null,
                    'name' => $data['stage_name'] ?? ($data['name'] ?? 'Ad-hoc Approval'),
                    'stage_order' => $newOrder,
                    'rule' => $data['rule'] ?? 'ALL',
                    'quorum_count' => $data['quorum_count'] ?? null,
                    'status' => 'PENDING',
                ]);
            }

            $stepInstance = ApprovalStepInstance::create([
                'approval_id' => $approval->id,
                'stage_instance_id' => $stageInstance->id,
                'step_id' => null,
                'name' => $data['name'] ?? 'Ad-hoc Approver',
                'order' => $data['order'] ?? 99,
                'user_id' => $data['user_id'] ?? null,
                'role_id' => $data['role_id'] ?? null,
                'status' => 'PENDING',
                'fields_schema' => $data['fields_schema'] ?? [],
                'is_adhoc' => true,
                'inserted_by' => $user?->id,
            ]);

            $this->logHistory($approval, $stepInstance, 'DELEGATE', $data['note'] ?? 'Ad-hoc approver inserted', $user);

            return $stepInstance;
        });
    }

    /**
     * Delegate the acting user's pending step to another user.
     */
    public function delegate(Approval $approval, int $toUserId, ?string $note = null, ?User $user = null): Delegation
    {
        $user = $user ?? Auth::user();

        return DB::transaction(function () use ($approval, $toUserId, $note, $user) {
            $delegation = Delegation::create([
                'approval_id' => $approval->id,
                'from_user_id' => $user?->id,
                'to_user_id' => $toUserId,
                'created_by' => $user?->id,
                'note' => $note,
                'status' => 'ACTIVE',
                'start_date' => now(),
            ]);

            $step = $approval->currentStageInstance?->stepInstances()
                ->where('status', 'PENDING')
                ->where('user_id', $user?->id)
                ->first();

            $this->logHistory($approval, $step, 'DELEGATE', $note, $user);

            return $delegation;
        });
    }

    /**
     * Take over the approval; the taker can then act on any pending step.
     */
    public function takeover(Approval $approval, ?string $note = null, ?User $user = null): void
    {
        $user = $user ?? Auth::user();
        $approval->update(['takeover_by' => $user?->id]);

        $step = $approval->currentStageInstance?->stepInstances()->where('status', 'PENDING')->first();
        $this->logHistory($approval, $step, 'TAKEOVER', $note, $user);
    }

    /**
     * Public accessor for UIs that need to know the user's actionable step.
     */
    public function resolveActableStepPublic(Approval $approval, ?User $user): ?ApprovalStepInstance
    {
        return $this->resolveActableStep($approval, $user);
    }

    /**
     * Find a pending step in the active stage that this user is allowed to act on.
     */
    protected function resolveActableStep(Approval $approval, ?User $user): ?ApprovalStepInstance
    {
        if (! $user) {
            return null;
        }

        $stage = $approval->currentStageInstance;

        if (! $stage || $stage->status !== 'ACTIVE') {
            return null;
        }

        $pending = $stage->stepInstances()->where('status', 'PENDING');

        // A user who took over the approval can act on any pending step.
        if ((int) $approval->takeover_by === (int) $user->id) {
            return $pending->orderBy('order')->first();
        }

        $roleIds = $user->roles->pluck('id')->all();

        $delegatedFrom = Delegation::where('approval_id', $approval->id)
            ->where('to_user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->pluck('from_user_id')
            ->all();

        return $pending->where(function ($query) use ($user, $roleIds, $delegatedFrom) {
            $query->where('user_id', $user->id);

            if (! empty($roleIds)) {
                $query->orWhereIn('role_id', $roleIds);
            }

            if (! empty($delegatedFrom)) {
                $query->orWhereIn('user_id', $delegatedFrom);
            }
        })->orderBy('order')->first();
    }

    /**
     * Evaluate the current stage's rule; advance to the next stage or finish.
     */
    protected function evaluateStage(Approval $approval): void
    {
        $stage = $approval->currentStageInstance;

        if (! $stage) {
            return;
        }

        $steps = $stage->stepInstances()->get();
        $approved = $steps->where('status', 'APPROVED')->count();
        $pending = $steps->where('status', 'PENDING')->count();

        $satisfied = match ($stage->rule) {
            'ANY' => $approved >= 1,
            'QUORUM' => $approved >= max(1, (int) $stage->quorum_count),
            default => $pending === 0 && $approved >= 1, // ALL
        };

        if (! $satisfied) {
            return;
        }

        // Rule met early (ANY / QUORUM): remaining approvers no longer needed.
        $stage->stepInstances()->where('status', 'PENDING')->update(['status' => 'SKIPPED']);
        $stage->update(['status' => 'APPROVED']);

        $next = $approval->stageInstances()
            ->where('stage_order', '>', $stage->stage_order)
            ->where('status', 'PENDING')
            ->orderBy('stage_order')
            ->first();

        if ($next) {
            $next->update(['status' => 'ACTIVE']);
            $approval->update(['current_stage_instance_id' => $next->id]);
        } else {
            $approval->update(['status' => 'APPROVED', 'current_stage_instance_id' => null]);
        }
    }

    /**
     * Validate required custom fields, then persist submitted values/attachments.
     */
    protected function captureFields(Approval $approval, ApprovalStepInstance $step, array $payload, ?User $user): void
    {
        $schema = $step->fields_schema ?? [];
        $fields = $payload['fields'] ?? [];
        $attachments = $payload['attachments'] ?? [];

        foreach ($schema as $def) {
            $key = $def['key'] ?? null;

            if (! $key || empty($def['is_required'])) {
                continue;
            }

            if (($def['type'] ?? 'text') === 'file') {
                if (empty($attachments[$key])) {
                    throw new RuntimeException("Field '{$def['label']}' wajib dilampirkan.");
                }
            } elseif (! isset($fields[$key]) || $fields[$key] === '' || $fields[$key] === null) {
                throw new RuntimeException("Field '{$def['label']}' wajib diisi.");
            }
        }

        foreach ($fields as $key => $value) {
            $def = collect($schema)->firstWhere('key', $key);

            ApprovalFieldValue::create([
                'approval_id' => $approval->id,
                'step_instance_id' => $step->id,
                'field_key' => $key,
                'label' => $def['label'] ?? $key,
                'type' => $def['type'] ?? 'text',
                'value' => is_array($value) ? json_encode($value) : $value,
            ]);
        }

        foreach ($attachments as $key => $files) {
            foreach ((array) $files as $file) {
                if (empty($file['path'])) {
                    continue;
                }

                ApprovalAttachment::create([
                    'approval_id' => $approval->id,
                    'step_instance_id' => $step->id,
                    'field_key' => $key,
                    'original_name' => $file['original_name'] ?? 'file',
                    'path' => $file['path'],
                    'mime' => $file['mime'] ?? null,
                    'size' => $file['size'] ?? null,
                    'uploaded_by' => $user?->id,
                ]);
            }
        }
    }

    protected function snapshotFields($step): array
    {
        return $step->fields->map(fn ($field) => [
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type,
            'is_required' => (bool) $field->is_required,
            'options' => $field->options,
        ])->values()->all();
    }

    protected function logHistory(Approval $approval, ?ApprovalStepInstance $step, string $action, ?string $note, ?User $user): void
    {
        ApprovalHistory::create([
            'approval_id' => $approval->id,
            'step_id' => $step?->step_id, // FK points at the template step
            'user_id' => $user?->id ?? Auth::id(),
            'action' => $action,
            'note' => $note,
        ]);
    }

    public function getStatus(Approval $approval): string
    {
        return $approval->status;
    }

    public function getHistory(Approval $approval)
    {
        return $approval->histories()->with('user')->latest()->get();
    }
}
