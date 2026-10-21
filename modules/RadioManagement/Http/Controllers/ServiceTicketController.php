<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Exports\ServiceTicketExport;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\ServiceTicket;
use Modules\RadioManagement\Models\ServiceTicketEvent;
use Modules\RadioManagement\Services\Like;
use Modules\RadioManagement\Services\ServiceTickets;
use Modules\SharePointDocs\Services\MenuAccess;

/**
 * "Servis & Perbaikan": the radios that are away for repair. A ticket is opened for a radio (Create), moved on a step at a
 * time and given notes (Update); whoever has the menu sees the board and the tickets (Read). What each step does to the
 * radio: ServiceTickets.
 */
class ServiceTicketController extends Controller
{
    public function __construct(private readonly ServiceTickets $tickets)
    {
    }

    /** What the page and its answers say while the tables are not there (the migration has not run). */
    public static function notReadyText(): string
    {
        return __('RadioManagement::service.not_ready');
    }

    public function index()
    {
        return view('RadioManagement::service.index', [
            'pageTitle' => __('RadioManagement::service.title'),
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => route('radio-management.service.index', [], false), 'label' => __('RadioManagement::service.title'), 'active' => true],
            ],
            'ready' => ServiceTicket::ready(),
            'statuses' => ServiceTicket::statusNames(),
            'placements' => array_values(array_filter(Placement::options(), fn (array $option) => in_array($option['value'], [Placement::DiUser->value, Placement::StokIt->value], true))),
            'conditions' => Condition::options(),
            'overdueDays' => ServiceTicket::OVERDUE_DAYS,
        ]);
    }

    /** The open tickets (the board and the list), the four numbers above them, and the service centers known. */
    public function list(): JsonResponse
    {
        if (! ServiceTicket::ready()) {
            return $this->notReady();
        }
        $today = now()->startOfDay();

        return response()->json([
            'data' => $this->tickets->openTickets()->map(fn (ServiceTicket $ticket) => $this->card($ticket, $today))->values(),
            'stats' => $this->tickets->stats($today),
            'centers' => $this->tickets->centers(),
        ]);
    }

    /** One ticket, open or closed (the drawer): its facts, the next step, the radio as it is now, the activity. */
    public function show(Request $request, int $ticket): JsonResponse
    {
        if (! ServiceTicket::ready()) {
            return $this->notReady();
        }
        $ticket = ServiceTicket::query()->with('events')->findOrFail($ticket);
        $radio = $ticket->radio_id ? Radio::withTrashed()->find($ticket->radio_id) : null;
        $mayOpenRadio = $radio !== null && ! $radio->trashed() && MenuAccess::allows($request->user(), 'radio-management.radio.detail');

        return response()->json(['data' => $this->card($ticket, now()->startOfDay()) + [
            'radio_label' => $ticket->radio_label,
            'document_no' => $ticket->document_no,
            'created_at' => $ticket->created_at?->toDateString(),
            'sent_at' => $ticket->sent_at?->toDateString(),
            'expected_at' => $ticket->expected_at?->toDateString(),
            'returned_at' => $ticket->returned_at?->toDateString(),
            'quote' => $ticket->quote,
            'cost' => $ticket->cost,
            'placement_before' => $ticket->placement_before,
            'next' => $ticket->nextStatus(),
            'radio_now' => $radio === null ? null : [
                'gone' => $radio->trashed(),
                'archived' => $radio->isArchived(),
                'placement' => $radio->placement?->display(),
                'condition' => $radio->condition?->display(),
                'url' => $mayOpenRadio ? route('radio-management.radio.detail', $radio) : null,
            ],
            'events' => $ticket->events->map(fn (ServiceTicketEvent $event) => [
                'kind' => $event->kind,
                'status' => $event->status,
                'note' => ServiceTickets::eventText($event),
                'user' => $event->user_name,
                'at' => $event->created_at?->format('Y-m-d H:i'),
            ])->values(),
        ]]);
    }

    /**
     * Radios for a new ticket (only for who may open one): the ones in the register, not archived, whose Radio ID, SN or
     * user is what was typed; the first 8, the Radio ID typed first. A radio with an open ticket says which.
     */
    public function radios(Request $request): JsonResponse
    {
        if (! MenuAccess::allows($request->user(), 'radio-management.service.store', 'Create')) {
            abort(403);
        }
        if (! ServiceTicket::ready()) {
            return $this->notReady();
        }
        $typed = mb_substr(trim((string) $request->query('q')), 0, 60);
        if ($typed === '') {
            return response()->json(['data' => []]);
        }
        $id = ctype_digit($typed) && strlen($typed) <= 9 ? (int) $typed : null;

        $radios = Radio::query()->active()->with(['radioType', 'department'])
            ->where(function (Builder $query) use ($typed, $id) {
                Like::any($query, ['serial_number', 'user_name'], $typed);
                if ($id !== null) {
                    $query->orWhere('dmr_id', $id);
                }
            })
            ->orderByRaw('case when dmr_id = ? then 0 else 1 end', [$id ?? -1])
            ->orderBy('dmr_id')->orderBy('id')
            ->limit(8)->get();
        $open = ServiceTicket::query()->whereIn('radio_id', $radios->modelKeys())->whereIn('status', ServiceTicket::OPEN)->pluck('number', 'radio_id');

        return response()->json(['data' => $radios->map(fn (Radio $radio) => [
            'id' => $radio->id,
            'dmr_id' => $radio->dmr_id,
            'sn' => $radio->serial_number,
            'type' => $radio->radioType?->name,
            'holder' => ServiceTickets::holder($radio),
            'placement' => $radio->placement?->display(),
            'open' => $open[$radio->id] ?? null,
        ])->values()]);
    }

    /** Every ticket (also the closed ones) that holds what was typed and went to that service center, as Excel. */
    public function export(Request $request)
    {
        if (! ServiceTicket::ready()) {
            return $this->notReady();
        }

        return ServiceTicketExport::download(
            $this->tickets->search($request->query('q'), $request->query('center'))->limit(5000)->get(),
            'servis-radio-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (! ServiceTicket::ready()) {
            return $this->notReady();
        }
        $validator = Validator::make($request->all(), [
            'radio_id' => ['required', 'integer'],
            'issue' => ['required', 'string', 'max:500'],
            'reported_by' => ['nullable', 'string', 'max:150'],
        ], self::messages(), self::fields());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($validator, $request) {
            $ticket = $this->tickets->open($validator->validated(), $request->user());

            return response()->json([
                'message' => __('RadioManagement::service.messages.created', ['number' => $ticket->number]),
                'data' => ['id' => $ticket->id, 'number' => $ticket->number],
            ], 201);
        });
    }

    /** The ticket moves on to its next step (ServiceTickets::move), with what that step asks for. */
    public function status(Request $request, int $ticket): JsonResponse
    {
        if (! ServiceTicket::ready()) {
            return $this->notReady();
        }
        $ticket = ServiceTicket::query()->findOrFail($ticket);
        $status = (string) $request->input('status');
        $today = now()->toDateString();
        $created = $ticket->created_at?->toDateString() ?? $today;
        $sent = $ticket->sent_at?->toDateString() ?? $today;
        // The day it goes, as typed (when it is a day), else today: what "expected back" may not be before.
        $sending = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input('sent_at')) ? (string) $request->input('sent_at') : $today;
        $rules = ['status' => ['required', Rule::in(array_keys(ServiceTicket::STATUSES))]] + match ($status) {
            ServiceTicket::SENT => [
                'service_center' => ['required', 'string', 'max:150'],
                'sent_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:' . $today, 'after_or_equal:' . $created],
                'document_no' => ['nullable', 'string', 'max:60'],
                'expected_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:' . $sending],
                'quote' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            ],
            ServiceTicket::IN_REPAIR => [
                'quote' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
                'expected_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:' . $sent],
            ],
            ServiceTicket::READY => [
                'cost' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            ],
            ServiceTicket::RETURNED => [
                'returned_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:' . $today, 'after_or_equal:' . $sent],
                'placement' => ['required', Rule::in([Placement::DiUser->value, Placement::StokIt->value])],
                'condition' => ['required', Rule::in(array_column(Condition::cases(), 'value'))],
                'cost' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            ],
            default => [],
        };
        $validator = Validator::make($request->all(), $rules, self::messages(), self::fields());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($ticket, $status, $validator, $request) {
            $ticket = $this->tickets->move($ticket, $status, $validator->validated(), $request->user());

            return response()->json([
                'message' => __('RadioManagement::service.messages.moved', ['number' => $ticket->number, 'status' => mb_strtolower($ticket->display())]),
                'data' => ['id' => $ticket->id, 'status' => $ticket->status],
            ]);
        });
    }

    public function note(Request $request, int $ticket): JsonResponse
    {
        if (! ServiceTicket::ready()) {
            return $this->notReady();
        }
        $ticket = ServiceTicket::query()->findOrFail($ticket);
        $validator = Validator::make($request->all(), [
            'note' => ['required', 'string', 'max:1000'],
        ], self::messages(), self::fields());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $this->tickets->note($ticket, (string) $validator->validated()['note'], $request->user());

        return response()->json(['message' => __('RadioManagement::service.messages.note_added', ['number' => $ticket->number])]);
    }

    /** What a field of the forms says when it is not right, in the language of the page (RadioManagement::service). */
    private static function messages(): array
    {
        return __('RadioManagement::service.rules') + __('RadioManagement::service.validation');
    }

    /** The names of the fields in those messages. */
    private static function fields(): array
    {
        return __('RadioManagement::service.fields');
    }

    /** What a card of the board and a row of the list show. */
    private function card(ServiceTicket $ticket, Carbon $today): array
    {
        return [
            'id' => $ticket->id,
            'number' => $ticket->number,
            'radio' => $ticket->radioName(),
            'radio_id' => $ticket->radio_id,
            'dmr_id' => $ticket->radio_dmr_id,
            'sn' => $ticket->radio_sn,
            'type' => $ticket->radio_type,
            'issue' => $ticket->issue,
            'reported_by' => $ticket->reported_by,
            'service_center' => $ticket->service_center,
            'status' => $ticket->status,
            'status_label' => $ticket->display(),
            'days' => $ticket->daysAway($today),
            'overdue' => $ticket->isOverdue($today),
        ];
    }

    /**
     * Runs a write on the register and turns the two ways it can be refused into the JSON the page expects: a refusal
     * found inside the lock, or the lock being busy.
     */
    private function saving(Closure $write): JsonResponse
    {
        try {
            return $write();
        } catch (ValidationException $e) {
            return $this->invalid($e->validator->errors()->all());
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => __('RadioManagement::service.messages.busy'),
                'errors' => [__('RadioManagement::service.messages.busy_text')],
            ], 409);
        }
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['message' => __('RadioManagement::service.messages.invalid'), 'errors' => $errors], 422);
    }

    private function notReady(): JsonResponse
    {
        return response()->json(['message' => self::notReadyText(), 'errors' => [self::notReadyText()]], 503);
    }
}
