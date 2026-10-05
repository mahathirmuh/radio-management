<?php

namespace Modules\RadioManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CheckMenuPermission;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Services\RadioImportService;
use RuntimeException;
use Throwable;

class RadioImportController extends Controller
{
    public const REPORT_CACHE_KEY = 'radio_import_last_report';

    public function __construct(private RadioImportService $importer)
    {
    }

    public function index()
    {
        return view('RadioManagement::import.index', [
            'pageTitle' => 'Import Excel',
            'menuItems' => [
                ['url' => '/', 'label' => 'Home', 'active' => false],
                ['url' => '/radio-management/import', 'label' => 'Import Excel', 'active' => true],
            ],
            'existing' => Radio::withTrashed()->count(),
            'lastReport' => Cache::get(self::REPORT_CACHE_KEY),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // "Hapus data lama" wipes the register and its history: Create on this menu is not enough,
        // the role must also be allowed to delete radios.
        if ($request->boolean('fresh') && ! $this->mayDeleteRadios($request)) {
            return response()->json([
                'message' => 'Tidak diizinkan',
                'errors' => ['Menghapus data lama membutuhkan izin Delete pada menu Register Radio.'],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'fresh' => ['nullable', 'boolean'],
            'konawe' => ['nullable', 'boolean'],
        ], [
            'required' => 'Pilih file Excel (.xlsx) terlebih dahulu.',
            'mimes' => 'File harus berformat .xlsx.',
            'max' => 'Ukuran file maksimal 20 MB.',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Input tidak valid', 'errors' => $validator->errors()->all()], 422);
        }

        set_time_limit(600);
        $file = $request->file('file');
        $path = $file->storeAs('imports', now()->format('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9.\-]+/', '_', $file->getClientOriginalName()), 'local');

        try {
            $report = $this->importer->import(Storage::disk('local')->path($path), [
                'fresh' => $request->boolean('fresh'),
                'konawe' => $request->boolean('konawe', true),
                'user_id' => $request->user()?->id,
                'file_name' => $file->getClientOriginalName(),
            ]);
        } catch (LockTimeoutException) {
            return response()->json([
                'message' => 'Register sedang sibuk',
                'errors' => ['Data radio sedang disimpan atau impor lain masih berjalan. Tunggu sebentar lalu coba lagi.'],
            ], 409);
        } catch (QueryException $e) {
            // A QueryException is a RuntimeException too: keep it out of the branch below, which
            // would show the raw SQL to the user and skip the log.
            Log::error('Radio import failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Impor gagal',
                'errors' => ['Terjadi kesalahan database saat impor, tidak ada data yang berubah. Detailnya ada di log aplikasi.'],
            ], 500);
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'Impor dibatalkan', 'errors' => [$e->getMessage()]], 422);
        } catch (Throwable $e) {
            Log::error('Radio import failed', ['exception' => $e]);

            return response()->json(['message' => 'Impor gagal', 'errors' => ['Terjadi kesalahan saat impor: ' . $e->getMessage()]], 500);
        }

        $report['at'] = now()->format('d-m-Y H:i');
        $report['by'] = $request->user()?->name;
        Cache::forever(self::REPORT_CACHE_KEY, $report);

        return response()->json(['message' => 'Impor selesai.', 'report' => $report]);
    }

    private function mayDeleteRadios(Request $request): bool
    {
        $user = $request->user();
        if ($user->roles->contains('id', 1)) {
            return true; // Super Admin
        }
        $menu = CheckMenuPermission::menuForKey('radio-management.radio');

        return $menu !== null
            && $user->guardRole($menu->id)
            && $user->hasMenuPermission($menu->id, CheckMenuPermission::PERMISSION_IDS['Delete']);
    }
}
