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
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioOpnameItem;
use Modules\RadioManagement\Models\RadioOpnameSession;
use Modules\RadioManagement\Models\ServiceTicket;
use Modules\RadioManagement\Services\RadioOpnames;
use Modules\SharePointDocs\Services\MenuAccess;

/**
 * "Stock Opname": counting the radios of one department on a phone (RadioOpnames). Whoever has the menu sees the sessions
 * (Read); starting one is a Create; scanning, reporting a problem and sending it to the register, an Update (K13f). Reporting
 * a problem opens a service ticket: it needs Create on Servis & Perbaikan too.
 */
class OpnameController extends Controller
{
    public const NOT_READY = 'Tabel stock opname belum dibuat di database. Jalankan "php artisan migrate" dulu.';

    /** The conditions a radio is found in (not "Belum dicek": it is being checked). */
    public const CONDITIONS = ['layak', 'perlu_service', 'rusak'];

    /** The page is in Indonesian: so are its messages. */
    private const MESSAGES = [
        'required' => ':attribute wajib diisi.',
        'in' => 'Pilihan :attribute tidak dikenal.',
        'integer' => ':attribute tidak dikenal.',
        'exists' => ':attribute tidak ada di master data.',
        'max' => ['string' => ':attribute paling panjang :max huruf.'],
    ];

    private const NAMES = [
        'department_id' => 'Department',
        'company_id' => 'Company',
        'radio_id' => 'Radio',
        'holder' => 'Pemegang',
        'holder_name' => 'Nama pemegang',
        'condition' => 'Kondisi',
        'note' => 'Catatan',
    ];

    public function __construct(private readonly RadioOpnames $opnames)
    {
    }

    public function index(Request $request)
    {
        $ready = RadioOpnameSession::ready();
        $user = $request->user();
        $scan = MenuAccess::allows($user, 'radio-management.opname.scan', 'Update');

        return view('RadioManagement::opname.index', [
            'ready' => $ready,
            'departments' => RadioDepartment::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'companies' => RadioCompany::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'overview' => $ready ? $this->opnames->overview() : ['open' => [], 'recent' => []],
            'conditions' => array_values(array_filter(Condition::options(), fn (array $option) => in_array($option['value'], self::CONDITIONS, true))),
            'may' => [
                'start' => MenuAccess::allows($user, 'radio-management.opname.start', 'Create'),
                'scan' => $scan,
                'report' => $scan && ServiceTicket::ready() && MenuAccess::allows($user, 'radio-management.service.store', 'Create'),
            ],
        ]);
    }

    /** How many radios are expected in a department (and company), its last session, the one open there. */
    public function outlook(Request $request): JsonResponse
    {
        if (! RadioOpnameSession::ready()) {
            return $this->notReady();
        }
        $validator = $this->validator($request->query(), $this->placeRules());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $data = $validator->validated();

        return response()->json(['data' => $this->opnames->outlook((int) $data['department_id'], isset($data['company_id']) ? (int) $data['company_id'] : null)]);
    }

    public function start(Request $request): JsonResponse
    {
        if (! RadioOpnameSession::ready()) {
            return $this->notReady();
        }
        $validator = $this->validator($request->all(), $this->placeRules());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($validator, $request) {
            $data = $validator->validated();
            $session = $this->opnames->start((int) $data['department_id'], isset($data['company_id']) ? (int) $data['company_id'] : null, $request->user());

            return response()->json(['data' => $this->opnames->state($session)], $session->wasRecentlyCreated ? 201 : 200);
        });
    }

    /** A session as its screens show it. */
    public function show(int $opname): JsonResponse
    {
        if (! RadioOpnameSession::ready()) {
            return $this->notReady();
        }

        return response()->json(['data' => $this->opnames->state(RadioOpnameSession::query()->findOrFail($opname))]);
    }

    /** The radio of what was typed or scanned (its Radio ID or SN), with what the session knows of it. */
    public function lookup(Request $request, int $opname): JsonResponse
    {
        if (! RadioOpnameSession::ready()) {
            return $this->notReady();
        }
        $session = RadioOpnameSession::query()->findOrFail($opname);
        $radio = $this->opnames->find(mb_substr((string) $request->query('q'), 0, 60));
        if ($radio === null) {
            return response()->json(['message' => 'Radio dengan Radio ID atau SN itu tidak ada di register.'], 404);
        }

        return response()->json(['data' => $this->opnames->card($session, $radio)]);
    }

    public function scan(Request $request, int $opname): JsonResponse
    {
        return $this->found($request, $opname, function (RadioOpnameSession $session, Radio $radio, array $data) use ($request) {
            $this->opnames->scan($session, $radio, $data, $request->user());

            return 'Radio ' . RadioOpnames::name($radio) . ' terverifikasi.';
        });
    }

    /** The scan saved, and a service ticket opened with the note (Create on Servis & Perbaikan too). */
    public function report(Request $request, int $opname): JsonResponse
    {
        if (! MenuAccess::allows($request->user(), 'radio-management.service.store', 'Create') || ! ServiceTicket::ready()) {
            return response()->json(['message' => 'Tidak diizinkan', 'errors' => ['Melaporkan masalah butuh izin membuat tiket di Servis & Perbaikan.']], 403);
        }

        return $this->found($request, $opname, function (RadioOpnameSession $session, Radio $radio, array $data) use ($request) {
            $ticket = $this->opnames->report($session, $radio, $data, $request->user());

            return "Tiket {$ticket->number} dibuat untuk radio " . RadioOpnames::name($radio) . '.';
        });
    }

    public function submit(Request $request, int $opname): JsonResponse
    {
        if (! RadioOpnameSession::ready()) {
            return $this->notReady();
        }
        $session = RadioOpnameSession::query()->findOrFail($opname);

        return $this->saving(function () use ($session, $request) {
            $summary = $this->opnames->submit($session, $request->user());

            return response()->json([
                'message' => "{$summary['verified']} radio terverifikasi di register.",
                'data' => $this->opnames->state($session->fresh()),
            ]);
        });
    }

    /** A scan or a report: the radio found, what was said of it, then the session as it is now. */
    private function found(Request $request, int $opname, Closure $write): JsonResponse
    {
        if (! RadioOpnameSession::ready()) {
            return $this->notReady();
        }
        $session = RadioOpnameSession::query()->findOrFail($opname);
        $validator = $this->validator($request->all(), [
            'radio_id' => ['required', 'integer'],
            'holder' => ['required', Rule::in(RadioOpnameItem::HOLDERS)],
            'holder_name' => ['nullable', 'string', 'max:150'],
            'condition' => ['required', Rule::in(self::CONDITIONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $data = $validator->validated();
        $radio = Radio::withTrashed()->find((int) $data['radio_id']);
        if ($radio === null) {
            return response()->json(['message' => 'Radio itu tidak ada di register.'], 404);
        }

        return $this->saving(fn () => response()->json([
            'message' => $write($session, $radio, $data),
            'data' => $this->opnames->state($session),
        ]));
    }

    private function placeRules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('radio_departments', 'id')],
            'company_id' => ['nullable', 'integer', Rule::exists('radio_companies', 'id')],
        ];
    }

    private function validator(array $values, array $rules): \Illuminate\Validation\Validator
    {
        return Validator::make($values, $rules, [
            'department_id.required' => 'Pilih department yang dihitung.',
        ] + self::MESSAGES, self::NAMES);
    }

    /**
     * Runs a write and turns the ways it can be refused into the JSON the page expects: a refusal (the session was sent,
     * the radio is archived ...), or the register being busy.
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
