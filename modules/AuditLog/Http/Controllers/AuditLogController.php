<?php

namespace Modules\AuditLog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The audit trail, read only (menu "Audit Log"). There is no action that changes or deletes an entry, by design; the
 * values were redacted when they were written, so nothing shown here can be a secret.
 */
class AuditLogController extends Controller
{
    /** Said while the table of the trail is not there yet (the code is live before its migration runs). */
    public const NOT_READY = 'The audit log is not switched on yet: its migration has not been run on this database.';

    public const VIEWS = ['table', 'timeline'];

    public function index(Request $request)
    {
        $ready = AuditLog::ready();

        return view('AuditLog::index', [
            'pageTitle' => 'Audit Log',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '/audit-log', 'label' => 'Audit Log', 'active' => true],
            ],
            'ready' => $ready,
            'actions' => $ready ? AuditLog::query()->distinct()->orderBy('action')->pluck('action') : collect(),
            // A link may open the page on the other view, an action or a person (?view=timeline&action=auth.login_failed).
            'view' => in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'table',
            'action' => self::text($request, 'action', 64),
            'search' => self::text($request, 'q', 255),
        ]);
    }

    /** A parameter as one line of text: anything else (a list, ?q[]=...) counts as nothing. */
    private static function text(Request $request, string $key, int $length): string
    {
        $value = $request->input($key);

        return is_string($value) ? trim(mb_substr($value, 0, $length)) : '';
    }

    public function list(Request $request): JsonResponse
    {
        if (! AuditLog::ready()) {
            return response()->json(['message' => self::NOT_READY], 503);
        }

        $limit = min(max((int) $request->input('paginated', 25), 1), 100);
        $search = self::text($request, 'search', 255);
        $action = self::text($request, 'action', 64);

        $entries = app(AuditLogQuery::class)->build($search, $action)->paginate($limit);

        return response()->json([
            'data' => $entries->getCollection()->map(fn (AuditLog $entry) => $this->row($entry))->values(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'from' => $entries->firstItem(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'to' => $entries->lastItem(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function row(AuditLog $entry): array
    {
        $old = is_array($entry->old_values) ? $entry->old_values : [];
        $new = is_array($entry->new_values) ? $entry->new_values : [];
        $changes = [];
        foreach (array_keys($new + $old) as $field) {
            $changes[] = [
                'field' => (string) $field,
                'old' => array_key_exists($field, $old) ? AuditLog::shown($old[$field]) : null,
                'new' => array_key_exists($field, $new) ? AuditLog::shown($new[$field]) : '-',
            ];
        }

        return [
            'id' => $entry->id,
            'at' => $entry->created_at?->format('d-m-Y H:i:s'),
            'ago' => $entry->created_at?->diffForHumans(),
            'user' => $entry->actorName(),
            'email' => $entry->user_email,
            'action' => $entry->action,
            'activity' => $entry->activityLabel(),
            'entity' => $entry->entityName(),
            'label' => $entry->entity_label,
            'changes' => $changes,
            'ip' => $entry->ip_address,
            // Through a token of the API ("API · Power BI (#3)"), or nothing: the pages.
            'via' => $entry->getAttribute('via'),
        ];
    }
}
