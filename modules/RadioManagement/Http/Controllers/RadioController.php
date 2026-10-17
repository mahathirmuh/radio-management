<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Exports\RadioExport;
use Modules\RadioManagement\Http\Requests\RadioRequest;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\ItSignature;
use Modules\RadioManagement\Models\ItSignatureUse;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Services\RadioCheckService;
use Modules\RadioManagement\Services\RadioFilter;
use Modules\RadioManagement\Services\RadioIdService;
use Modules\RadioManagement\Services\RadioService;

class RadioController extends Controller
{
    protected $pageTitle;
    protected $menuItems;

    public function __construct(
        private RadioService $radios,
        private RadioFilter $filter,
        private RadioCheckService $checks,
        private RadioIdService $ids,
    ) {
        $this->pageTitle = 'Register Radio';
        $this->menuItems = [
            ['url' => '/', 'label' => 'Home', 'active' => false],
            ['url' => '/radio-management/radio', 'label' => 'Register Radio', 'active' => true],
        ];
    }

    public function index()
    {
        return view('RadioManagement::radio.index', [
            'pageTitle' => $this->pageTitle,
            'menuItems' => $this->menuItems,
        ] + $this->formOptions());
    }

    public function list(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('paginated', 10), 1), 100);
        $data = $this->filter->paginate($request, $limit);

        return response()->json([
            'data' => $data->getCollection()->map(fn (Radio $radio) => $this->row($radio))->values(),
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $data->currentPage(),
                'from' => $data->firstItem(),
                'last_page' => $data->lastPage(),
                'path' => $request->url(),
                'per_page' => $data->perPage(),
                'to' => $data->lastItem(),
                'total' => $data->total(),
            ],
        ]);
    }

    /** The lowest free Radio IDs; with `after`, the next free ones above that ID. */
    public function nextId(Request $request): JsonResponse
    {
        $after = $request->filled('after')
            ? max(0, min($request->integer('after'), (int) config('radio.id_max')))
            : null;
        $free = $this->ids->nextFree($after === null ? 5 : 3, $request->integer('except') ?: null, $after);

        return response()->json([
            'data' => array_map(fn (int $id) => ['id' => $id, 'mdc' => RadioIdService::mdc($id)], $free),
        ]);
    }

    public function export(Request $request)
    {
        return RadioExport::download(
            $this->filter->all($request),
            $this->checks,
            'register-radio-' . now()->format('Ymd-His') . '.xlsx'
        );
    }

    public function show(Radio $radio): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $radio->id,
                'dmr_id' => $radio->dmr_id,
                'mdc' => $radio->mdc_code,
                'serial_number' => $radio->serial_number,
                'radio_type_id' => $radio->radio_type_id,
                'owner_company_id' => $radio->owner_company_id,
                'user_name' => $radio->user_name,
                'department_id' => $radio->department_id,
                'company_id' => $radio->company_id,
                'channel_template_id' => $radio->channel_template_id,
                'last_programmed_at' => $radio->last_programmed_at?->toDateString(),
                'placement' => $radio->placement->value,
                'condition' => $radio->condition->value,
                'last_verified_at' => $radio->last_verified_at?->toDateString(),
                'document_no' => $radio->document_no,
                'notes' => $radio->notes,
                'needs_review' => $radio->needs_review,
                'review_notes' => $radio->review_notes,
                'archived' => $radio->isArchived(),
                'version' => $radio->version(),
            ],
        ]);
    }

    public function detail(Radio $radio)
    {
        $radio->load(['radioType', 'ownerCompany', 'company', 'department', 'channelTemplate', 'histories.user', 'creator', 'updater']);

        return view('RadioManagement::radio.detail', [
            'pageTitle' => 'Detail Radio',
            'menuItems' => [
                ...$this->menuItems,
                ['url' => route('radio-management.radio.detail', $radio), 'label' => $radio->display_name, 'active' => true],
            ],
            'radio' => $radio,
            'issues' => $this->checks->issuesFor($radio),
            'historyActions' => config('radio.history_actions'),
            'archiveReasons' => config('radio.archive_reasons'),
        ] + $this->formOptions());
    }

    /**
     * The "IT Equipment Checkout Form" (MTI-HRM-ICT-FRM-001) for one radio, as a page of its own:
     * what the register knows is filled in, the rest is left to be written in (on screen, before
     * printing) or by hand. Nothing of the form is saved.
     *
     * The box "PROCESSED BY (ICT Person)" carries the signature of whoever opens the form, when that account has one in
     * "E-Sign Master": its own and nobody else's, so that the signature says who processed it. That it was put on a form
     * is written down (ItSignatureUse).
     */
    public function checkoutForm(Request $request, Radio $radio)
    {
        $radio->load(['radioType', 'department']);
        // A page that is printed and signed on carries nothing of the developer's tools: their bar would lie over the box that is signed in.
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        $signature = ItSignature::of($request->user());
        if ($signature !== null) {
            ItSignatureUse::create([
                'signature_id' => $signature->id,
                'user_id' => $request->user()->id,
                'user_name' => mb_substr((string) $request->user()->name, 0, 255),
                'radio_id' => $radio->id,
                'radio_label' => mb_substr(trim('ID ' . ($radio->dmr_id ?? '-') . ' · SN ' . ($radio->serial_number ?? '-') . ' · ' . ($radio->user_name ?? '-')), 0, 255),
                'created_at' => now(),
            ]);
            app(AuditLogger::class)->log(AuditLogger::ESIGN_USED, $radio, new: ['Signature' => $signature->display_name], label: $radio->display_name);
        }

        return view('RadioManagement::radio.checkout-form', [
            'radio' => $radio,
            // Which of "HT / RIG" is struck out: the one this radio is not.
            'category' => $radio->radioType?->category,
            'today' => now()->format('d-m-Y'),
            // The signature of the person at ICT who opens the form, or null: the box is then signed on paper.
            'ictSignature' => $signature === null ? null : ['image' => $signature->image, 'name' => $signature->display_name],
        ]);
    }

    public function store(RadioRequest $request): JsonResponse
    {
        return $this->saving(function () use ($request) {
            $radio = $this->radios->create(
                Arr::except($request->validated(), ['review_done', 'version']),
                $request->user()?->id
            );

            return response()->json(['message' => 'Radio berhasil ditambahkan.', 'id' => $radio->id]);
        });
    }

    public function update(RadioRequest $request, Radio $radio): JsonResponse
    {
        $data = Arr::except($request->validated(), ['review_done', 'version']);
        if ($request->boolean('review_done')) {
            $data['needs_review'] = false;
        }

        return $this->saving(function () use ($request, $radio, $data) {
            $this->radios->update($radio, $data, $request->user()?->id, $request->input('version'));

            return response()->json(['message' => 'Data radio berhasil disimpan.']);
        });
    }

    public function archive(Request $request, Radio $radio): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'archive_reason' => ['required', Rule::in(config('radio.archive_reasons'))],
            'archived_at' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [], ['archive_reason' => 'Alasan', 'archived_at' => 'Tanggal', 'note' => 'Catatan']);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($request, $radio) {
            $this->radios->archive(
                $radio,
                $request->input('archive_reason'),
                $request->input('archived_at'),
                $request->input('note'),
                $request->user()?->id
            );

            return response()->json(['message' => 'Radio diarsipkan.']);
        });
    }

    public function restore(Request $request, Radio $radio): JsonResponse
    {
        return $this->saving(function () use ($request, $radio) {
            $this->radios->restore($radio, $request->user()?->id);

            return response()->json(['message' => 'Radio dipulihkan.', 'dmr_id' => $radio->dmr_id]);
        });
    }

    public function storeHistory(Request $request, Radio $radio): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => ['required', Rule::in(config('radio.history_actions'))],
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:2000'],
            'document_no' => ['nullable', 'string', 'max:100'],
        ], [], ['action' => 'Jenis', 'event_date' => 'Tanggal', 'description' => 'Keterangan', 'document_no' => 'No. Dokumen']);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }

        return $this->saving(function () use ($request, $radio, $validator) {
            $this->radios->addHistory($radio, $validator->validated(), $request->user()?->id);

            return response()->json(['message' => 'Riwayat ditambahkan.']);
        });
    }

    public function destroy(Request $request, Radio $radio): JsonResponse
    {
        return $this->saving(function () use ($request, $radio) {
            $this->radios->delete($radio, $request->user()?->id);

            return response()->json(['message' => 'Radio dihapus.']);
        });
    }

    private function row(Radio $radio): array
    {
        return [
            'id' => $radio->id,
            'dmr_id' => $radio->dmr_id,
            'previous_dmr_id' => $radio->previous_dmr_id,
            'mdc' => $radio->mdc_code,
            'serial_number' => $radio->serial_number,
            'type' => $radio->radioType?->name,
            'category' => $radio->radioType?->category,
            'user_name' => $radio->user_name,
            'department' => $radio->department?->name,
            'company' => $radio->company?->code,
            'owner' => $radio->ownerCompany?->code,
            // MTI or Non MTI, by the company that owns it; null while that is not filled in.
            'owner_group' => $radio->ownerGroup(),
            'template' => $radio->channelTemplate?->name,
            'placement' => ['value' => $radio->placement->value, 'label' => $radio->placement->label(), 'color' => $radio->placement->color()],
            'condition' => ['value' => $radio->condition->value, 'label' => $radio->condition->label(), 'color' => $radio->condition->color()],
            'last_programmed_at' => $radio->last_programmed_at?->format('d-m-Y'),
            'last_verified_at' => $radio->last_verified_at?->format('d-m-Y'),
            'archived' => $radio->isArchived(),
            'archived_at' => $radio->archived_at?->format('d-m-Y'),
            'archive_reason' => $radio->archive_reason,
            'issues' => $this->checks->issuesFor($radio),
            'detail_url' => route('radio-management.radio.detail', $radio),
            'print_url' => route('radio-management.radio.checkout-form', $radio),
        ];
    }

    private function formOptions(): array
    {
        return [
            'types' => RadioType::orderBy('name')->get(),
            'companies' => RadioCompany::orderBy('code')->get(),
            'departments' => RadioDepartment::orderBy('name')->get(),
            'templates' => ChannelTemplate::orderBy('name')->get(),
            'placements' => Placement::options(),
            'conditions' => Condition::options(),
            'issues' => RadioCheckService::ISSUES,
            'archiveReasons' => config('radio.archive_reasons'),
        ];
    }

    /**
     * Runs a register write and turns the two ways it can be refused into the JSON the forms
     * expect: a conflict found inside the write lock, or the lock being busy.
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
}
