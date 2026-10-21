<?php

namespace Modules\RadioManagement\Services;

use App\Http\Middleware\CheckMenuPermission;
use App\Models\UserManagement\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * An import of the Excel register (sheet "Form", and "to Konawe") as the page "Import Excel" and the API run it: who
 * may replace the register, what is checked of the file, and what is kept afterwards (the report of the last import,
 * one entry of the audit trail). The reading of the workbook itself is RadioImportService's.
 */
class RadioImports
{
    /** The report of the last import, shown on the page and by the API. */
    public const REPORT_CACHE_KEY = 'radio_import_last_report';

    public function __construct(private RadioImportService $importer)
    {
    }

    /** Why "fresh" was refused, in the language of the request. */
    public static function replaceRefused(): string
    {
        return __('RadioManagement::import.messages.replace_refused');
    }

    /** What is checked of the request, with the messages of the page. */
    public static function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'fresh' => ['nullable', 'boolean'],
            'konawe' => ['nullable', 'boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'required' => __('RadioManagement::import.messages.file_required'),
            'mimes' => __('RadioManagement::import.messages.file_type'),
            'max' => __('RadioManagement::import.messages.file_size'),
        ];
    }

    /**
     * "Hapus data lama" wipes the register and its history: Create on "Import Excel" is not enough, the role must also be
     * allowed to delete radios.
     */
    public function mayReplace(User $user): bool
    {
        if ($user->roles->contains('id', 1)) {
            return true; // Super Admin
        }
        $menu = CheckMenuPermission::menuForKey('radio-management.radio');

        return $menu !== null
            && $user->guardRole($menu->id)
            && $user->hasMenuPermission($menu->id, CheckMenuPermission::PERMISSION_IDS['Delete']);
    }

    /**
     * Imports the file and returns its report. Nothing is changed when it throws.
     *
     * @throws LockTimeoutException while a save or another import holds the register
     * @throws ImportProblem when the file is refused, or the import fails
     */
    public function run(UploadedFile $file, bool $fresh, bool $konawe, ?User $user): array
    {
        set_time_limit(600);
        $path = $file->storeAs('imports', now()->format('Ymd-His') . '-' . preg_replace('/[^A-Za-z0-9.\-]+/', '_', $file->getClientOriginalName()), 'local');

        try {
            $report = $this->importer->import(Storage::disk('local')->path($path), [
                'fresh' => $fresh,
                'konawe' => $konawe,
                'user_id' => $user?->id,
                'file_name' => $file->getClientOriginalName(),
            ]);
        } catch (LockTimeoutException $e) {
            throw $e;
        } catch (QueryException $e) {
            // A QueryException is a RuntimeException too: keep it out of the branch below, which
            // would show the raw SQL to the user and skip the log.
            Log::error('Radio import failed', ['exception' => $e]);

            throw ImportProblem::failed(__('RadioManagement::import.messages.database'));
        } catch (RuntimeException $e) {
            throw ImportProblem::refused($e->getMessage());
        } catch (Throwable $e) {
            Log::error('Radio import failed', ['exception' => $e]);

            throw ImportProblem::failed(__('RadioManagement::import.messages.error', ['error' => $e->getMessage()]));
        }

        $report['at'] = now()->format('d-m-Y H:i');
        $report['by'] = $user?->name;
        Cache::forever(self::REPORT_CACHE_KEY, $report);
        // One entry for the whole file (the radios it made each get their line in "Riwayat Radio").
        app(AuditLogger::class)->log(AuditLogger::RADIO_IMPORTED, new: [
            'File' => $report['file'] ?? $file->getClientOriginalName(),
            'Hapus data lama' => ($report['fresh'] ?? false) ? 'Ya' : 'Tidak',
            'Diimpor' => $report['imported'] ?? 0,
            'Aktif' => $report['imported_active'] ?? 0,
            'Arsip' => $report['imported_archived'] ?? 0,
            'Perlu dicek' => $report['flagged'] ?? 0,
            'Dilewati' => count($report['skipped'] ?? []),
        ]);

        return $report;
    }

    /** The report of the last import, or null when there has been none (since the cache was last emptied). */
    public function lastReport(): ?array
    {
        $report = Cache::get(self::REPORT_CACHE_KEY);

        return is_array($report) ? $report : null;
    }
}
