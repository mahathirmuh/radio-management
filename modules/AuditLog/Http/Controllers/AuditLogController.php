<?php

namespace Modules\AuditLog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Log\AuditLog;
use App\Services\Audit\AuditLogQuery;
use App\Services\Audit\UserAgent;
use Carbon\CarbonImmutable;
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

    /** The ranges of days the page names (the browser turns them into the days of "from" and "to"); "all" is no range. */
    public const RANGES = ['today', 'yesterday', '7d', '30d'];

    public function index(Request $request)
    {
        $ready = AuditLog::ready();
        // A link may name a range (?range=7d), or its days (?from=2026-10-12&to=2026-10-19).
        [$from, $to] = self::days($request);
        $range = $from && $to ? 'custom' : (in_array($request->query('range'), self::RANGES, true) ? $request->query('range') : 'all');

        return view('AuditLog::index', [
            'pageTitle' => 'Audit Log',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '/audit-log', 'label' => 'Audit Log', 'active' => true],
            ],
            'ready' => $ready,
            'actions' => $ready ? AuditLog::query()->distinct()->orderBy('action')->pluck('action') : collect(),
            'entities' => $ready ? self::entities() : [],
            // A link may open the page on the other view, an action or a person (?view=timeline&action=auth.login_failed).
            'view' => in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'table',
            'action' => self::text($request, 'action', 64),
            'entity' => self::text($request, 'entity', 64),
            'search' => self::text($request, 'q', 255),
            'range' => $range,
            'from' => $range === 'custom' ? $from->format('Y-m-d') : '',
            'to' => $range === 'custom' ? $to->format('Y-m-d') : '',
        ]);
    }

    /**
     * The kinds of record the trail has entries about, by the names the page gives them, in the order of those names.
     *
     * @return array<string, string> entity type => name
     */
    private static function entities(): array
    {
        return AuditLog::query()->whereNotNull('entity_type')->distinct()->pluck('entity_type')
            ->mapWithKeys(fn (string $type) => [$type => AuditLog::entityLabel($type)])
            ->sort()
            ->all();
    }

    /**
     * The first and the last day of the entries asked for ("from", "to": YYYY-MM-DD, days of the application's time zone), either of
     * them null; given the wrong way round, they are turned. Anything that is no day is none.
     *
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private static function days(Request $request): array
    {
        [$from, $to] = [self::day($request, 'from'), self::day($request, 'to')];

        return $from && $to && $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private static function day(Request $request, string $key): ?CarbonImmutable
    {
        $value = $request->input($key);
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('app.timezone'));

        // (A day that does not exist, 2026-02-31, would be read as another one.)
        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $value ? $day : null;
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
        $entity = self::text($request, 'entity', 64);
        [$from, $to] = self::days($request);

        $entries = app(AuditLogQuery::class)->build($search, $action, $from, $to, $entity === '' ? null : $entity)->paginate($limit);

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
            // "Chrome 141 on Windows", from the User-Agent that was kept.
            'browser' => UserAgent::describe($entry->user_agent),
            // Through a token of the API ("API · Power BI (#3)"), or nothing: the pages.
            'via' => $entry->getAttribute('via'),
        ];
    }
}
