<?php

namespace Modules\ApprovalManagement\Http\Controllers;

use App\Helpers\Cores\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\ApprovalManagement\Models\ApprovalFlow;
use Modules\ApprovalManagement\Models\ApprovalStage;
use Modules\ApprovalManagement\Models\ApprovalStep;
use Yajra\DataTables\DataTables;

class ApiApprovalManagementController extends Controller
{
    /* ===================================================================
     |  FLOWS
     * =================================================================== */

    public function datatableFlows()
    {
        $data = ApprovalFlow::query()->withCount('stages');

        return datatables()->of($data)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                return '
                    <button class="btn btn-sm button-action btn-success" data-id="'.$row->id.'" data-action="Edit">Edit</button>
                    <button class="btn btn-sm button-action btn-info" data-id="'.$row->id.'" data-action="View">View</button>
                    <button class="btn btn-sm button-action btn-danger" data-id="'.$row->id.'" data-action="Delete">Delete</button>
                ';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function flowStore(Request $request)
    {
        $validatedData = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation error',
                'errors'  => $validatedData->errors(),
            ], 422);
        }

        $approvalFlow = ApprovalFlow::create([
            'name'        => $request->name,
            'description' => $request->description,
            'status'      => $request->status,
        ]);

        return JsonResponse::status(true)
            ->message('Approval Flow created successfully.')
            ->data($approvalFlow->toArray())
            ->code(201)
            ->send();
    }

    public function flowUpdate(Request $request, $id)
    {
        $validatedData = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:ACTIVE,INACTIVE',
        ]);

        $approvalFlow = ApprovalFlow::find($id);

        if (! $approvalFlow) {
            return JsonResponse::status(false)->message('Approval Flow not found.')->code(404)->send();
        }

        $approvalFlow->update($validatedData);

        return JsonResponse::status(true)
            ->message('Approval Flow updated successfully.')
            ->data($approvalFlow->toArray())
            ->code(200)
            ->send();
    }

    public function flowShow($id)
    {
        $approvalFlow = ApprovalFlow::find($id);

        if (! $approvalFlow) {
            return JsonResponse::status(false)->message('Approval Flow not found.')->code(404)->send();
        }

        return JsonResponse::status(true)
            ->message('Approval Flow retrieved successfully.')
            ->data($approvalFlow->toArray())
            ->code(200)
            ->send();
    }

    public function flowDestroy($id)
    {
        $approvalFlow = ApprovalFlow::find($id);

        if (! $approvalFlow) {
            return JsonResponse::status(false)->message('Approval Flow not found.')->code(404)->send();
        }

        $approvalFlow->delete();

        return JsonResponse::status(true)->message('Approval Flow deleted successfully.')->code(200)->send();
    }

    /* ===================================================================
     |  STAGES  (sequential groups; own the parallel steps + rule)
     * =================================================================== */

    public function stageDatatable(Request $request)
    {
        $query = ApprovalStage::withCount('steps')
            ->where('approval_flow_id', $request->flow_id)
            ->orderBy('stage_order');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('rule_label', function ($row) {
                return $row->rule === 'QUORUM'
                    ? 'QUORUM ('.$row->quorum_count.')'
                    : $row->rule;
            })
            ->addColumn('action', function ($row) {
                return '
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-info stage-action" data-action="View" data-id="'.$row->id.'" data-flow-id="'.$row->approval_flow_id.'"><i class="ti ti-eye"></i></button>
                        <button type="button" class="btn btn-sm btn-warning stage-action" data-action="Edit" data-id="'.$row->id.'" data-flow-id="'.$row->approval_flow_id.'"><i class="ti ti-edit"></i></button>
                        <button type="button" class="btn btn-sm btn-danger stage-action" data-action="Delete" data-id="'.$row->id.'" data-flow-id="'.$row->approval_flow_id.'"><i class="ti ti-trash"></i></button>
                    </div>
                ';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function stageStore(Request $request)
    {
        $request->validate([
            'approval_flow_id' => 'required|exists:approval_flows,id',
            'name'             => 'required|string|max:255',
            'stage_order'      => 'required|integer|min:1',
            'rule'             => 'required|in:ALL,ANY,QUORUM',
            'quorum_count'     => 'nullable|integer|min:1|required_if:rule,QUORUM',
        ]);

        $exists = ApprovalStage::where('approval_flow_id', $request->approval_flow_id)
            ->where('stage_order', $request->stage_order)
            ->exists();

        if ($exists) {
            return JsonResponse::status(false)->message('A stage with this order already exists in this flow.')->code(422)->send();
        }

        $stage = ApprovalStage::create([
            'approval_flow_id' => $request->approval_flow_id,
            'name'             => $request->name,
            'stage_order'      => $request->stage_order,
            'rule'             => $request->rule,
            'quorum_count'     => $request->rule === 'QUORUM' ? $request->quorum_count : null,
        ]);

        return JsonResponse::status(true)->message('Stage created successfully.')->data($stage->toArray())->code(201)->send();
    }

    public function stageShow($id)
    {
        $stage = ApprovalStage::find($id);

        if (! $stage) {
            return JsonResponse::status(false)->message('Stage not found.')->code(404)->send();
        }

        return JsonResponse::status(true)->message('Stage retrieved successfully.')->data($stage->toArray())->code(200)->send();
    }

    public function stageUpdate(Request $request, $id)
    {
        $stage = ApprovalStage::find($id);

        if (! $stage) {
            return JsonResponse::status(false)->message('Stage not found.')->code(404)->send();
        }

        $request->validate([
            'stage_order'  => 'required|integer|min:1',
            'name'         => 'required|string|max:255',
            'rule'         => 'required|in:ALL,ANY,QUORUM',
            'quorum_count' => 'nullable|integer|min:1|required_if:rule,QUORUM',
        ]);

        $exists = ApprovalStage::where('approval_flow_id', $stage->approval_flow_id)
            ->where('stage_order', $request->stage_order)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return JsonResponse::status(false)->message('A stage with this order already exists in this flow.')->code(422)->send();
        }

        $stage->update([
            'name'         => $request->name,
            'stage_order'  => $request->stage_order,
            'rule'         => $request->rule,
            'quorum_count' => $request->rule === 'QUORUM' ? $request->quorum_count : null,
        ]);

        return JsonResponse::status(true)->message('Stage updated successfully.')->data($stage->toArray())->code(200)->send();
    }

    public function stageDestroy($id)
    {
        $stage = ApprovalStage::findOrFail($id);
        $stage->delete();

        return JsonResponse::status(true)->message('Stage deleted successfully.')->code(200)->send();
    }

    /* ===================================================================
     |  STEPS  (approvers; may run in parallel within a stage)
     * =================================================================== */

    public function stepDatatable(Request $request)
    {
        $query = ApprovalStep::with(['user', 'role'])
            ->withCount('fields')
            ->where('stage_id', $request->stage_id)
            ->orderBy('step_order');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                return '
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-info steps-action" data-action="View" data-id="'.$row->id.'" data-stage-id="'.$row->stage_id.'"><i class="ti ti-eye"></i></button>
                        <button type="button" class="btn btn-sm btn-warning steps-action" data-action="Edit" data-id="'.$row->id.'" data-stage-id="'.$row->stage_id.'"><i class="ti ti-edit"></i></button>
                        <button type="button" class="btn btn-sm btn-danger steps-action" data-action="Delete" data-id="'.$row->id.'" data-stage-id="'.$row->stage_id.'"><i class="ti ti-trash"></i></button>
                    </div>
                ';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function stepStore(Request $request)
    {
        $request->validate([
            'approval_flow_id' => 'required|exists:approval_flows,id',
            'stage_id'         => 'required|exists:approval_stages,id',
            'name'             => 'required|string|max:255',
            'step_order'       => 'required|integer|min:1',
            'user_id'          => 'nullable|exists:users,id',
            'role_id'          => 'nullable|exists:roles,id',
        ]);

        if (! $request->user_id && ! $request->role_id) {
            return JsonResponse::status(false)->message('Either a user or a role must be selected.')->code(422)->send();
        }

        $step = DB::transaction(function () use ($request) {
            $step = ApprovalStep::create([
                'approval_flow_id' => $request->approval_flow_id,
                'stage_id'         => $request->stage_id,
                'name'             => $request->name,
                'step_order'       => $request->step_order,
                'user_id'          => $request->user_id,
                'role_id'          => $request->role_id,
                'is_final_step'    => $request->boolean('is_final_step'),
                'is_special_case'  => $request->boolean('is_special_case'),
            ]);

            $this->syncFields($step, $request->input('fields', []));

            return $step;
        });

        return JsonResponse::status(true)->message('Approval step created successfully.')->data($step->load('fields')->toArray())->code(201)->send();
    }

    public function show($id)
    {
        $step = ApprovalStep::with(['user', 'role', 'fields'])->findOrFail($id);

        return JsonResponse::status(true)->message('Approval step retrieved successfully.')->data($step->toArray())->code(200)->send();
    }

    public function stepUpdate(Request $request, $id)
    {
        $step = ApprovalStep::findOrFail($id);

        $request->validate([
            'stage_id'   => 'required|exists:approval_stages,id',
            'name'       => 'required|string|max:255',
            'step_order' => 'required|integer|min:1',
            'user_id'    => 'nullable|exists:users,id',
            'role_id'    => 'nullable|exists:roles,id',
        ]);

        if (! $request->user_id && ! $request->role_id) {
            return JsonResponse::status(false)->message('Either a user or a role must be selected.')->code(422)->send();
        }

        DB::transaction(function () use ($request, $step) {
            $step->update([
                'stage_id'        => $request->stage_id,
                'name'            => $request->name,
                'step_order'      => $request->step_order,
                'user_id'         => $request->user_id,
                'role_id'         => $request->role_id,
                'is_final_step'   => $request->boolean('is_final_step'),
                'is_special_case' => $request->boolean('is_special_case'),
            ]);

            $this->syncFields($step, $request->input('fields', []));
        });

        return JsonResponse::status(true)->message('Approval step updated successfully.')->data($step->load('fields')->toArray())->code(200)->send();
    }

    public function stepDestroy($id)
    {
        $step = ApprovalStep::findOrFail($id);
        $step->delete();

        return JsonResponse::status(true)->message('Approval step deleted successfully.')->code(200)->send();
    }

    /**
     * Replace a step's custom field definitions with the submitted set.
     *
     * @param  array  $fields  list of ['key','label','type','is_required','options']
     */
    protected function syncFields(ApprovalStep $step, $fields): void
    {
        $step->fields()->delete();

        foreach ((array) $fields as $i => $field) {
            $label = trim($field['label'] ?? '');

            if ($label === '') {
                continue;
            }

            $key = $field['key'] ?? null;
            $key = $key ?: \Illuminate\Support\Str::slug($label, '_');

            $options = $field['options'] ?? null;
            if (is_string($options)) {
                $options = array_values(array_filter(array_map('trim', explode(',', $options))));
            }

            $step->fields()->create([
                'key'         => $key,
                'label'       => $label,
                'type'        => $field['type'] ?? 'text',
                'is_required' => filter_var($field['is_required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'options'     => $options ?: null,
                'sort_order'  => $i,
            ]);
        }
    }

    /* ===================================================================
     |  DELEGATE / TAKEOVER (list endpoints — see ApprovalInboxController for actions)
     * =================================================================== */

    public function datatableDelegate()
    {
        $rows = \Modules\ApprovalManagement\Models\Delegation::with(['fromUser', 'toUser', 'approval'])->latest();

        return DataTables::of($rows)
            ->addIndexColumn()
            ->addColumn('from', fn ($r) => $r->fromUser->name ?? '-')
            ->addColumn('to', fn ($r) => $r->toUser->name ?? '-')
            ->make(true);
    }

    public function datatableTakeover()
    {
        $rows = \Modules\ApprovalManagement\Models\Approval::whereNotNull('takeover_by')
            ->with(['takeoverUser', 'flow'])->latest();

        return DataTables::of($rows)
            ->addIndexColumn()
            ->addColumn('taken_by', fn ($r) => $r->takeoverUser->name ?? '-')
            ->addColumn('flow_name', fn ($r) => $r->flow->name ?? '-')
            ->make(true);
    }
}
