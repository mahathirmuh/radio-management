<?php

namespace Modules\Api\Http\Controllers\V1;

use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Api\Http\Resources\AuditLogResource;


/**
 * The audit trail of the page "Audit Log", read only: nothing changes or deletes an entry.
 *
 * @tags Audit log
 */
class AuditLogController extends ApiController
{
    /**
     * List audit entries
     *
     * Newest first. The values were redacted when they were written: no password, token or signature is in them.
     */
    public function index(Request $request, AuditLogQuery $entries): AnonymousResourceCollection|JsonResponse
    {
        $request->validate([
            /** The e-mail of who did it, the name of that account now, or what was typed into a failed sign-in. */
            'search' => ['nullable', 'string', 'max:255'],
            /** One action, e.g. "auth.login_failed" or "radio.updated". */
            'action' => ['nullable', 'string', 'max:64'],
            /** From this day (YYYY-MM-DD). */
            'date_from' => ['nullable', 'date'],
            /** Up to and with this day. */
            'date_to' => ['nullable', 'date'],
            /** What the entries are about: "Radio", "User", "Role", "RadioType", "ItSignature", ... */
            'entity_type' => ['nullable', 'string', 'max:64'],
            /** With entity_type: the id of that record, e.g. the whole trail of one radio. */
            'entity_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        if (! AuditLog::ready()) {
            return response()->json(['message' => __('Api::api.audit_not_ready')], 503);
        }

        return AuditLogResource::collection($entries->build(
            trim((string) $request->input('search')),
            trim((string) $request->input('action')),
            $request->date('date_from'),
            $request->date('date_to'),
            $request->input('entity_type'),
            $request->filled('entity_id') ? $request->integer('entity_id') : null,
        )->paginate($this->perPage($request)));
    }
}
