<?php

namespace Modules\Api\Http\Controllers\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Modules\Api\Http\Requests\RadioApiRequest;
use Modules\Api\Http\Requests\RadioListRequest;
use Modules\Api\Http\Resources\RadioHistoryResource;
use Modules\Api\Http\Resources\RadioResource;
use Modules\RadioManagement\Exports\RadioExport;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Services\CheckoutForm;
use Modules\RadioManagement\Services\RadioCheckService;
use Modules\RadioManagement\Services\RadioConflicts;
use Modules\RadioManagement\Services\RadioFilter;
use Modules\RadioManagement\Services\RadioIdService;
use Modules\RadioManagement\Services\RadioRules;
use Modules\RadioManagement\Services\RadioService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The register of radios, with the rules of the pages: every change goes through RadioService, so it is checked the
 * same way (unique Radio ID and SN, no Radio ID with hex F, one save at a time), lands in "Riwayat Radio" and in the
 * audit trail.
 *
 * @tags Radios
 */
class RadioController extends ApiController
{
    private const RELATIONS = ['radioType', 'ownerCompany', 'company', 'department', 'channelTemplate'];

    public function __construct(
        private RadioService $radios,
        private RadioFilter $filter,
        private RadioIdService $ids,
        private RadioConflicts $conflicts,
    ) {
    }

    /**
     * List radios
     *
     * The filters of the page "Register Radio". A filter of several values takes a list (`type[]=1&type[]=2`) or
     * comma separated values (`type=1,2`).
     */
    public function index(RadioListRequest $request): AnonymousResourceCollection
    {
        return RadioResource::collection($this->filter->paginate($request->forFilter(), $this->perPage($request)));
    }

    /**
     * Export radios to Excel
     *
     * The file of the button "Export" of the page: every radio that matches the filters of the list (no paging), as an
     * .xlsx file. 10 a minute per token.
     *
     * @response \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(RadioListRequest $request, RadioCheckService $checks): BinaryFileResponse
    {
        return RadioExport::download($this->filter->all($request->forFilter()), $checks, 'register-radio-' . now()->format('Ymd-His') . '.xlsx');
    }

    /**
     * The checkout form of a radio
     *
     * What the "IT Equipment Checkout Form" (button "Cetak") fills in for the radio, for an application that prints the
     * form its own way. The keys of `fields` are the boxes of the form; the signature of the person at ICT is not given
     * out by the API.
     */
    public function checkoutForm(Radio $radio, CheckoutForm $form): JsonResponse
    {
        return response()->json(['data' => ['radio_id' => $radio->id] + $form->for($radio)]);
    }

    /**
     * Show a radio
     */
    public function show(Radio $radio): RadioResource
    {
        return new RadioResource($radio->load(self::RELATIONS));
    }

    /**
     * Register a radio
     *
     * As the form of the page: SN, type, Posisi and Kondisi are required; a Radio ID must be free and its MDC code may
     * not contain F; the user/unit is required when the radio is "Di User".
     */
    public function store(RadioApiRequest $request)
    {
        return $this->write(function () use ($request) {
            $radio = $this->radios->create(Arr::except($request->validated(), ['review_done', 'version']), $request->user()->id);

            return (new RadioResource($radio->fresh(self::RELATIONS)))->response()->setStatusCode(201);
        });
    }

    /**
     * Change a radio
     *
     * Send the fields to change; Posisi and Kondisi are always sent, as on the form. Send the `version` you read with the
     * radio: if somebody changed it since, nothing is saved and the answer is 409. `review_done: true` clears "Perlu
     * dicek".
     */
    public function update(RadioApiRequest $request, Radio $radio)
    {
        $data = Arr::except($request->validated(), ['review_done', 'version']);
        if ($request->boolean('review_done')) {
            $data['needs_review'] = false;
        }

        return $this->write(fn () => new RadioResource(
            $this->radios->update($radio, $data, $request->user()->id, $request->input('version'))->fresh(self::RELATIONS)
        ));
    }

    /**
     * Archive a radio
     *
     * Takes it out of service; its Radio ID is released. An Update on "Register Radio", as on the page.
     */
    public function archive(Request $request, Radio $radio)
    {
        [$rules, $names] = RadioRules::archive();
        $data = $request->validate($rules, [], $names);

        return $this->write(fn () => new RadioResource(
            $this->radios->archive($radio, $data['archive_reason'], $data['archived_at'], $data['note'] ?? null, $request->user()->id)->fresh(self::RELATIONS)
        ));
    }

    /**
     * Restore an archived radio
     *
     * Its old Radio ID comes back unless another radio has it now (then `dmr_id` stays empty).
     */
    public function restore(Request $request, Radio $radio)
    {
        return $this->write(fn () => new RadioResource($this->radios->restore($radio, $request->user()->id)->fresh(self::RELATIONS)));
    }

    /**
     * Delete a radio
     *
     * For a radio entered by mistake (its history is kept). A radio that left for good is archived instead.
     */
    public function destroy(Request $request, Radio $radio)
    {
        return $this->write(function () use ($request, $radio) {
            $this->radios->delete($radio, $request->user()->id);

            return response()->noContent();
        });
    }

    /**
     * The history of a radio
     *
     * Newest first.
     */
    public function history(Radio $radio): AnonymousResourceCollection
    {
        return RadioHistoryResource::collection(
            $radio->histories()->with(['user', 'radio'])->reorder()->orderByDesc('event_date')->orderByDesc('id')->get()
        );
    }

    /**
     * Add a line to the history of a radio
     *
     * "Program" and "Verifikasi" also move the radio's own date forward. An Update on "Register Radio", as on the page.
     */
    public function addHistory(Request $request, Radio $radio)
    {
        [$rules, $names] = RadioRules::history();
        $data = $request->validate($rules, [], $names);

        return $this->write(fn () => (new RadioHistoryResource(
            $this->radios->addHistory($radio, $data, $request->user()->id)->load(['user', 'radio'])
        ))->response()->setStatusCode(201));
    }

    /**
     * The next free Radio IDs
     *
     * Free: not used by an active radio, not kept for an archived one, and without F in the MDC code.
     */
    public function freeIds(Request $request): JsonResponse
    {
        $request->validate([
            /** How many, 1 to 50 (5 when not given). */
            'count' => ['nullable', 'integer', 'min:1', 'max:50'],
            /** Only IDs above this one. */
            'after' => ['nullable', 'integer', 'min:0'],
        ]);
        $after = $request->filled('after') ? min($request->integer('after'), (int) config('radio.id_max')) : null;

        return response()->json([
            'data' => array_map(
                fn (int $id) => ['dmr_id' => $id, 'mdc_code' => RadioIdService::mdc($id)],
                $this->ids->nextFree($request->integer('count', 5), null, $after)
            ),
        ]);
    }

    /**
     * Check a Radio ID and an SN
     *
     * Whether they could be given to a radio now, with the reason when not (the messages of the form).
     */
    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'dmr_id' => ['nullable', 'integer', 'min:1', 'max:' . config('radio.id_max')],
            'serial_number' => ['nullable', 'string', 'max:50'],
            /** The radio being changed, whose own ID and SN do not count. */
            'radio' => ['nullable', 'integer'],
        ]);
        $except = $request->filled('radio') ? $request->integer('radio') : null;
        $answer = [];
        if ($request->filled('dmr_id')) {
            $message = $this->conflicts->radioId($request->integer('dmr_id'), $except);
            $answer['dmr_id'] = ['value' => $request->integer('dmr_id'), 'mdc_code' => RadioIdService::mdc($request->integer('dmr_id')), 'free' => $message === null, 'message' => $message];
        }
        if ($request->filled('serial_number')) {
            $serial = RadioConflicts::normalizeSerial((string) $request->input('serial_number'));
            $message = $this->conflicts->serial($serial, $except);
            $answer['serial_number'] = ['value' => $serial, 'free' => $message === null, 'message' => $message];
        }

        return response()->json(['data' => (object) $answer]);
    }
}
