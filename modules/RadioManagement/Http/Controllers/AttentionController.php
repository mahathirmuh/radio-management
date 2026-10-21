<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Models\RadioCheckState;
use Modules\RadioManagement\Services\AttentionInbox;
use Modules\RadioManagement\Services\RadioCheckService;
use Modules\SharePointDocs\Services\MenuAccess;

/**
 * "Perlu Perhatian": the results of the register's checks as items (AttentionInbox). Whoever has the menu sees them
 * (Read); giving an item to somebody, setting it aside and fixing it are an Update. A fix changes a radio, so it also
 * needs Update on Register Radio. Its words are in RadioManagement::attention, in the language of the page.
 */
class AttentionController extends Controller
{
    /** How many items of a check one answer holds (the rest come with "Tampilkan lagi"). */
    private const PAGE = 50;

    public function __construct(private readonly AttentionInbox $inbox)
    {
    }

    public function index(Request $request)
    {
        return view('RadioManagement::attention.index', [
            'pageTitle' => 'Perlu Perhatian',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => route('radio-management.attention.index', [], false), 'label' => 'Perlu Perhatian', 'active' => true],
            ],
            'ready' => RadioCheckState::ready(),
            // (Each with its name in the language of the page.)
            'checks' => collect(RadioCheckService::ISSUES)->map(fn (array $issue, string $code) => ['label' => RadioCheckService::label($code)] + $issue)->all(),
            'people' => $this->inbox->assignable(),
            // What the role may do here (the server checks again): give, set aside; and fix, which changes a radio.
            'may' => [
                'update' => MenuAccess::allows($request->user(), 'radio-management.attention.dismiss', 'Update'),
                'fix' => MenuAccess::allows($request->user(), 'radio-management.attention.fix', 'Update')
                    && MenuAccess::allows($request->user(), AttentionInbox::RADIO_UPDATE, 'Update'),
            ],
        ]);
    }

    /** The open items of every check (counted), and those of one check (a page of them). */
    public function list(Request $request): JsonResponse
    {
        $check = (string) $request->query('check', '');
        if (! isset(RadioCheckService::ISSUES[$check])) {
            $check = (string) array_key_first(RadioCheckService::ISSUES);
        }
        $who = in_array($request->query('who'), ['mine', 'none'], true) ? (string) $request->query('who') : 'all';
        $page = max(1, (int) $request->query('page', 1));

        $counts = $this->inbox->counts();
        $items = $this->inbox->list($check, $who, $request->user());

        return response()->json([
            'check' => $check,
            'counts' => $counts,
            'total' => count($items),
            'data' => array_slice($items, ($page - 1) * self::PAGE, self::PAGE),
            'more' => count($items) > $page * self::PAGE,
            'page' => $page,
            'ready' => RadioCheckState::ready(),
        ]);
    }

    /** One item: what is wrong, its radios side by side, the fixes, who has it. */
    public function show(Request $request): JsonResponse
    {
        $detail = $this->inbox->detail((string) $request->query('check'), (string) $request->query('subject'));
        if ($detail === null) {
            return response()->json(['message' => __('RadioManagement::attention.messages.gone')], 404);
        }

        return response()->json(['data' => $detail]);
    }

    public function assign(Request $request): JsonResponse
    {
        if (! RadioCheckState::ready()) {
            return $this->notReady();
        }
        $validator = $this->validator($request, ['user_id' => ['nullable', 'integer']]);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($validator, $request) {
            $data = $validator->validated();
            $person = $this->inbox->assign($data['check'], (string) $data['subject'], isset($data['user_id']) ? (int) $data['user_id'] : null, $request->user());

            return response()->json(['message' => $person
                ? __('RadioManagement::attention.messages.assigned', ['name' => $person->name])
                : __('RadioManagement::attention.messages.unassigned')]);
        });
    }

    public function dismiss(Request $request): JsonResponse
    {
        if (! RadioCheckState::ready()) {
            return $this->notReady();
        }
        $validator = $this->validator($request, ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => __('RadioManagement::attention.messages.reason_required')]);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($validator, $request) {
            $data = $validator->validated();
            $this->inbox->dismiss($data['check'], (string) $data['subject'], (string) $data['reason'], $request->user());

            return response()->json(['message' => __('RadioManagement::attention.messages.dismissed')]);
        });
    }

    /** Applies a fix (it changes a radio: Update on Register Radio too). */
    public function fix(Request $request): JsonResponse
    {
        if (! MenuAccess::allows($request->user(), AttentionInbox::RADIO_UPDATE, 'Update')) {
            return response()->json([
                'message' => __('RadioManagement::attention.messages.not_allowed'),
                'errors' => [__('RadioManagement::attention.messages.fix_needs_register')],
            ], 403);
        }
        $validator = $this->validator($request, [
            'option' => ['required', 'string', 'max:60'],
            'value' => ['nullable', 'string', 'max:200'],
        ], ['option.required' => __('RadioManagement::attention.messages.option_required')]);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($validator, $request) {
            $data = $validator->validated();
            $done = $this->inbox->fix($data['check'], (string) $data['subject'], (string) $data['option'], $data['value'] ?? null, $request->user());

            return response()->json(['message' => $done]);
        });
    }

    /** The item (check and subject) every change names, and the rules of the change itself. */
    private function validator(Request $request, array $rules, array $messages = []): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'check' => ['required', Rule::in(array_keys(RadioCheckService::ISSUES))],
            'subject' => ['required', 'string', 'max:100'],
        ] + $rules, $messages + __('RadioManagement::attention.validation'), __('RadioManagement::attention.fields'));
    }

    /**
     * Runs a change and turns the ways it can be refused into the JSON the page expects: a refusal (the item is gone, the
     * value is taken ...), or the register being busy.
     */
    private function saving(Closure $write): JsonResponse
    {
        try {
            return $write();
        } catch (ValidationException $e) {
            return $this->invalid($e->validator->errors()->all());
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => __('RadioManagement::register.messages.busy'),
                'errors' => [__('RadioManagement::register.messages.busy_text')],
            ], 409);
        }
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['message' => __('RadioManagement::register.messages.invalid'), 'errors' => $errors], 422);
    }

    private function notReady(): JsonResponse
    {
        $message = __('RadioManagement::attention.not_ready');

        return response()->json(['message' => $message, 'errors' => [$message]], 503);
    }
}
