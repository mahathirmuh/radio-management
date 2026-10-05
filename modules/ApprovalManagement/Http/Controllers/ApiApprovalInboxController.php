<?php

namespace Modules\ApprovalManagement\Http\Controllers;

use App\Helpers\Cores\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\ApprovalManagement\Models\Approval;
use Modules\ApprovalManagement\Models\Delegation;
use Modules\ApprovalManagement\Services\ApprovalService;
use Throwable;
use Yajra\DataTables\DataTables;

class ApiApprovalInboxController extends Controller
{
    public function __construct(protected ApprovalService $service)
    {
    }

    /**
     * Approvals that are waiting on the current user (assigned, by role,
     * delegated, or taken over).
     */
    public function datatable(Request $request)
    {
        $query = $this->actionableQuery(Auth::user())
            ->with(['flow', 'currentStageInstance']);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('flow_name', fn ($r) => $r->flow->name ?? '-')
            ->addColumn('stage_name', fn ($r) => $r->currentStageInstance->name ?? '-')
            ->addColumn('subject', fn ($r) => class_basename($r->model_type).' #'.$r->model_id)
            ->addColumn('action', function ($row) {
                return '<button class="btn btn-sm btn-primary inbox-open" data-id="'.$row->id.'"><i class="ti ti-eye"></i> Review</button>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * Full detail for the review screen, including the step the current user
     * must act on and its required custom fields.
     */
    public function show($id)
    {
        $approval = Approval::with([
            'flow',
            'stageInstances.stepInstances.user',
            'stageInstances.stepInstances.role',
            'stageInstances.stepInstances.actedBy',
            'histories.user',
        ])->findOrFail($id);

        $myStep = $this->service->resolveActableStepPublic($approval, Auth::user());

        return JsonResponse::status(true)
            ->message('Approval retrieved successfully.')
            ->data([
                'approval'  => $approval->toArray(),
                'my_step'   => $myStep?->toArray(),
                'can_act'   => (bool) $myStep,
            ])
            ->code(200)
            ->send();
    }

    /**
     * Approve / reject / revise the current user's step.
     */
    public function act(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject,revise',
            'note'   => 'nullable|string',
        ]);

        $approval = Approval::findOrFail($id);

        $attachments = [];
        foreach ((array) $request->file('attachments', []) as $key => $files) {
            foreach ((array) $files as $file) {
                $path = $file->store('approval-attachments', 'public');
                $attachments[$key][] = [
                    'original_name' => $file->getClientOriginalName(),
                    'path'          => $path,
                    'mime'          => $file->getClientMimeType(),
                    'size'          => $file->getSize(),
                ];
            }
        }

        $payload = [
            'note'        => $request->input('note'),
            'fields'      => $request->input('fields', []),
            'attachments' => $attachments,
        ];

        try {
            $approval = $this->service->act($approval, $request->input('action'), $payload, Auth::user());
        } catch (Throwable $e) {
            return JsonResponse::status(false)->message($e->getMessage())->code(422)->send();
        }

        return JsonResponse::status(true)
            ->message('Action recorded. Approval is now '.$approval->status.'.')
            ->data(['status' => $approval->status])
            ->code(200)
            ->send();
    }

    /**
     * Insert an ad-hoc approver at runtime.
     */
    public function insertStep(Request $request, $id)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'position'     => 'required|in:into_current_stage,new_stage_after_current',
            'user_id'      => 'nullable|exists:users,id',
            'role_id'      => 'nullable|exists:roles,id',
            'rule'         => 'nullable|in:ALL,ANY,QUORUM',
            'quorum_count' => 'nullable|integer|min:1',
        ]);

        if (! $request->user_id && ! $request->role_id) {
            return JsonResponse::status(false)->message('Select a user or a role for the ad-hoc approver.')->code(422)->send();
        }

        $approval = Approval::findOrFail($id);

        try {
            $this->service->insertAdhocStep($approval, [
                'name'         => $request->name,
                'position'     => $request->position,
                'user_id'      => $request->user_id,
                'role_id'      => $request->role_id,
                'rule'         => $request->rule ?? 'ALL',
                'quorum_count' => $request->quorum_count,
                'note'         => $request->input('note'),
            ], Auth::user());
        } catch (Throwable $e) {
            return JsonResponse::status(false)->message($e->getMessage())->code(422)->send();
        }

        return JsonResponse::status(true)->message('Ad-hoc approver inserted.')->code(200)->send();
    }

    public function delegate(Request $request, $id)
    {
        $request->validate([
            'to_user_id' => 'required|exists:users,id',
            'note'       => 'nullable|string',
        ]);

        $approval = Approval::findOrFail($id);

        try {
            $this->service->delegate($approval, (int) $request->to_user_id, $request->input('note'), Auth::user());
        } catch (Throwable $e) {
            return JsonResponse::status(false)->message($e->getMessage())->code(422)->send();
        }

        return JsonResponse::status(true)->message('Approval delegated.')->code(200)->send();
    }

    public function takeover(Request $request, $id)
    {
        $approval = Approval::findOrFail($id);

        try {
            $this->service->takeover($approval, $request->input('note'), Auth::user());
        } catch (Throwable $e) {
            return JsonResponse::status(false)->message($e->getMessage())->code(422)->send();
        }

        return JsonResponse::status(true)->message('You have taken over this approval.')->code(200)->send();
    }

    /**
     * Base query: approvals pending on this user.
     */
    protected function actionableQuery($user)
    {
        $roleIds = $user->roles->pluck('id')->all();
        $delegatedFrom = Delegation::where('to_user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->pluck('from_user_id')
            ->all();

        return Approval::query()
            ->where('status', 'PENDING')
            ->where(function ($outer) use ($user, $roleIds, $delegatedFrom) {
                $outer->whereHas('currentStageInstance.stepInstances', function ($q) use ($user, $roleIds, $delegatedFrom) {
                    $q->where('status', 'PENDING')->where(function ($w) use ($user, $roleIds, $delegatedFrom) {
                        $w->where('user_id', $user->id);
                        if (! empty($roleIds)) {
                            $w->orWhereIn('role_id', $roleIds);
                        }
                        if (! empty($delegatedFrom)) {
                            $w->orWhereIn('user_id', $delegatedFrom);
                        }
                    });
                })->orWhere('takeover_by', $user->id);
            });
    }
}
