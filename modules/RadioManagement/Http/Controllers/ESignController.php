<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\RadioManagement\Models\ItSignature;
use Modules\RadioManagement\Models\ItSignatureUse;
use Modules\RadioManagement\Services\Like;
use Modules\RadioManagement\Services\SignatureImage;

/**
 * "E-Sign Master": the signatures of the people at ICT. Whoever manages this page (its menu; Super Admin) registers
 * the signature of an account, drawn by that person on the pad, and can replace it, switch it off or remove it.
 *
 * Registering a signature here does not let anybody put it on a form: the IT Equipment Checkout Form shows, in
 * "PROCESSED BY", the signature of the account that opens the form, and no other (RadioController::checkoutForm).
 */
class ESignController extends Controller
{
    private const NOT_READY = 'Tabel tanda tangan belum dibuat di database. Jalankan "php artisan migrate" dulu.';

    public function index()
    {
        return view('RadioManagement::e-sign.index', [
            'pageTitle' => 'E-Sign Master',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '#', 'label' => 'Master Data Radio', 'active' => false],
                ['url' => route('radio-management.e-sign.index', [], false), 'label' => 'E-Sign Master', 'active' => true],
            ],
        ]);
    }

    /** The page the pad is on: shown in a frame of the dialog, where the person signs. Nothing is saved by it. */
    public function pad()
    {
        // The developer's bar would lie over the box that is signed in.
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        return view('RadioManagement::e-sign.pad', ['today' => now()->format('d-m-Y')]);
    }

    public function list(Request $request): JsonResponse
    {
        if (! ItSignature::ready()) {
            return response()->json(['message' => self::NOT_READY], 503);
        }

        $limit = min(max((int) $request->input('paginated', 25), 1), 100);
        $search = trim((string) $request->input('search'));
        $page = ItSignature::query()
            ->with('user:id,name,email,deleted_at')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                Like::any($q, ['display_name'], $search);
                $q->orWhereIn('user_id', User::withTrashed()->where(fn ($u) => Like::any($u, ['name', 'email'], $search))->select('id'));
            }))
            ->orderBy('display_name')
            ->paginate($limit);

        $ids = $page->getCollection()->modelKeys();
        $uses = ItSignatureUse::query()->whereIn('signature_id', $ids)
            ->selectRaw('signature_id, count(*) as total, max(created_at) as last_at')->groupBy('signature_id')->get()->keyBy('signature_id');

        return response()->json([
            'data' => $page->getCollection()->map(fn (ItSignature $one) => [
                'id' => $one->id,
                'user_id' => $one->user_id,
                'account' => $one->user?->name,
                'email' => $one->user?->email,
                // An account that was removed keeps its row here, switched off for good: it cannot sign in.
                'account_gone' => $one->user === null || $one->user->trashed(),
                'display_name' => $one->display_name,
                'active' => $one->active,
                'image' => $one->image,
                'uses' => (int) ($uses[$one->id]->total ?? 0),
                'last_used' => isset($uses[$one->id]) ? Carbon::parse($uses[$one->id]->last_at)->format('d-m-Y H:i') : null,
                'updated_at' => $one->updated_at?->format('d-m-Y H:i'),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'from' => $page->firstItem(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'to' => $page->lastItem(),
                'total' => $page->total(),
            ],
            // The accounts that have no signature yet: the ones a new one can be registered for.
            'candidates' => User::query()->people()->whereNotIn('id', ItSignature::query()->select('user_id'))
                ->orderBy('name')->limit(500)->get(['id', 'name', 'email'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])->values(),
        ]);
    }

    /** Where a signature was put: the latest forms first. */
    public function uses(int $id): JsonResponse
    {
        $signature = ItSignature::findOrFail($id);

        return response()->json([
            'data' => ItSignatureUse::query()->where('signature_id', $signature->id)->orderByDesc('id')->limit(100)->get()
                ->map(fn (ItSignatureUse $use) => [
                    'at' => $use->created_at?->format('d-m-Y H:i'),
                    'user' => $use->user_name,
                    'radio' => $use->radio_label,
                ])->values(),
            'total' => ItSignatureUse::query()->where('signature_id', $signature->id)->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! ItSignature::ready()) {
            return response()->json(['message' => self::NOT_READY], 503);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('it_signatures', 'user_id')],
            'display_name' => ['nullable', 'string', 'max:150'],
            'image' => ['required', 'string'],
        ], [
            'user_id.required' => 'Pilih akun pemilik tanda tangan.',
            'user_id.exists' => 'Akun itu tidak ditemukan.',
            'user_id.unique' => 'Akun itu sudah punya tanda tangan. Ganti lewat tombol Edit.',
            'image.required' => 'Tanda tangan belum ada. Klik kotaknya untuk tanda tangan di layar, atau unggah file gambarnya.',
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $data = $validator->validated();
        try {
            $image = SignatureImage::clean($data['image']);
        } catch (InvalidArgumentException $e) {
            return $this->invalid([$e->getMessage()]);
        }

        $user = User::findOrFail($data['user_id']);
        $name = trim((string) ($data['display_name'] ?? '')) ?: (string) $user->name;
        $signature = ItSignature::create([
            'user_id' => $user->id,
            'display_name' => mb_substr($name, 0, 150),
            'image' => $image,
            'active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);
        // Never the picture: that one was made, for whom, and the name under it.
        app(AuditLogger::class)->log(AuditLogger::ESIGN_CREATED, $signature, new: $this->facts($signature, $user), label: $user->email);

        return response()->json(['message' => "Tanda tangan {$name} berhasil didaftarkan."]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $signature = ItSignature::findOrFail($id);
        $validator = Validator::make($request->all(), [
            'display_name' => ['required', 'string', 'max:150'],
            'active' => ['required', 'boolean'],
            // Only when the signature itself is replaced.
            'image' => ['nullable', 'string'],
        ], [
            'display_name.required' => 'Nama yang tercetak di bawah tanda tangan harus diisi.',
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->all());
        }
        $data = $validator->validated();
        $changes = [
            'display_name' => trim($data['display_name']),
            'active' => (bool) $data['active'],
            'updated_by' => $request->user()?->id,
        ];
        if (filled($data['image'] ?? null)) {
            try {
                $changes['image'] = SignatureImage::clean($data['image']);
            } catch (InvalidArgumentException $e) {
                return $this->invalid([$e->getMessage()]);
            }
        }
        $owner = User::withTrashed()->find($signature->user_id);
        $before = $this->facts($signature, $owner);
        $signature->update($changes);
        app(AuditLogger::class)->logChanges(AuditLogger::ESIGN_UPDATED, $signature, $before,
            $this->facts($signature, $owner) + (isset($changes['image']) ? ['Signature' => 'Replaced'] : []), $owner?->email);

        return response()->json(['message' => "Tanda tangan {$signature->display_name} berhasil disimpan."]);
    }

    /** Removes the signature. The record of where it was put stays. */
    public function destroy(int $id): JsonResponse
    {
        $signature = ItSignature::findOrFail($id);
        $name = $signature->display_name;
        $owner = User::withTrashed()->find($signature->user_id);
        $before = $this->facts($signature, $owner);
        DB::transaction(fn () => $signature->delete());
        app(AuditLogger::class)->log(AuditLogger::ESIGN_DELETED, $signature, old: $before, label: $owner?->email);

        return response()->json(['message' => "Tanda tangan {$name} dihapus."]);
    }

    /**
     * A signature as the audit trail shows it: whose it is and the name printed under it. Never the picture.
     *
     * @return array<string, string|null>
     */
    private function facts(ItSignature $signature, ?User $owner): array
    {
        return [
            'Account' => $owner?->email,
            'Name printed' => $signature->display_name,
            'Active' => $signature->active ? 'Yes' : 'No',
        ];
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['message' => 'Input tidak valid', 'errors' => $errors], 422);
    }
}
