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
 * needs Update on Register Radio.
 */
class AttentionController extends Controller
{
    public const NOT_READY = 'Tabel Perlu Perhatian belum dibuat di database. Jalankan "php artisan migrate" dulu.';

    /** The page is in Indonesian: so are its messages. */
    private const MESSAGES = [
        'required' => ':attribute wajib diisi.',
        'in' => 'Pilihan :attribute tidak dikenal.',
        'integer' => ':attribute tidak dikenal.',
        'max' => ['string' => ':attribute paling panjang :max huruf.'],
    ];

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
            'checks' => RadioCheckService::ISSUES,
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
            return response()->json(['message' => 'Item ini sudah tidak ada: datanya sudah dibetulkan atau berubah.'], 404);
        }

        return response()->json(['data' => $detail]);
    }

    public function assign(Request $request): JsonResponse
    {
        if (! RadioCheckState::ready()) {
            return $this->notReady();
        }
        $validator = $this->validator($request, ['user_id' => ['nullable', 'integer']], ['user_id' => 'Petugas']);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($validator, $request) {
            $data = $validator->validated();
            $person = $this->inbox->assign($data['check'], (string) $data['subject'], isset($data['user_id']) ? (int) $data['user_id'] : null, $request->user());

            return response()->json(['message' => $person ? "Item ditugaskan ke {$person->name}." : 'Item tidak lagi ditugaskan ke siapa pun.']);
        });
    }

    public function dismiss(Request $request): JsonResponse
    {
        if (! RadioCheckState::ready()) {
            return $this->notReady();
        }
        $validator = $this->validator($request, ['reason' => ['required', 'string', 'max:500']], ['reason' => 'Alasan'],
            ['reason.required' => 'Tulis alasan item ini diabaikan.']);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($validator, $request) {
            $data = $validator->validated();
            $this->inbox->dismiss($data['check'], (string) $data['subject'], (string) $data['reason'], $request->user());

            return response()->json(['message' => 'Item diabaikan. Muncul lagi bila data radionya berubah.']);
        });
    }

    /** Applies a fix (it changes a radio: Update on Register Radio too). */
    public function fix(Request $request): JsonResponse
    {
        if (! MenuAccess::allows($request->user(), AttentionInbox::RADIO_UPDATE, 'Update')) {
            return response()->json(['message' => 'Tidak diizinkan', 'errors' => ['Memperbaiki data radio butuh izin Update di Register Radio.']], 403);
        }
        $validator = $this->validator($request, [
            'option' => ['required', 'string', 'max:60'],
            'value' => ['nullable', 'string', 'max:200'],
        ], ['option' => 'Perbaikan', 'value' => 'Isian'], ['option.required' => 'Pilih salah satu perbaikan.']);
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
    private function validator(Request $request, array $rules, array $names, array $messages = []): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'check' => ['required', Rule::in(array_keys(RadioCheckService::ISSUES))],
            'subject' => ['required', 'string', 'max:100'],
        ] + $rules, $messages + self::MESSAGES, ['check' => 'Cek', 'subject' => 'Item'] + $names);
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
                'message' => 'Register sedang sibuk',
                'errors' => ['Data radio lain sedang disimpan, atau impor Excel sedang berjalan. Coba lagi beberapa saat lagi.'],
            ], 409);
        }
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Input tidak valid', 'errors' => $errors], 422);
    }

    private function notReady(): JsonResponse
    {
        return response()->json(['message' => self::NOT_READY, 'errors' => [self::NOT_READY]], 503);
    }
}
