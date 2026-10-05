<?php

namespace Modules\RadioManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\RadioManagement\Http\Controllers\RadioImportController;
use Modules\RadioManagement\Services\RadioImportService;
use RuntimeException;

class ImportRadioRegister extends Command
{
    protected $signature = 'radio:import
        {file? : Path to the .xlsx register (default: "Radio Register 07-08-25.xlsx" in the project root)}
        {--fresh : Delete existing radios and their history before importing}
        {--no-konawe : Skip the "to Konawe" sheet}';

    protected $description = 'Import the legacy Excel radio register (sheet "Form") into the database';

    public function handle(RadioImportService $importer): int
    {
        $path = $this->argument('file') ?: base_path('Radio Register 07-08-25.xlsx');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $this->info("Importing {$path} ...");

        try {
            $report = $importer->import($path, [
                'fresh' => (bool) $this->option('fresh'),
                'konawe' => ! $this->option('no-konawe'),
                'file_name' => basename($path),
            ]);
        } catch (LockTimeoutException) {
            $this->error('Data radio sedang disimpan atau impor lain masih berjalan. Coba lagi sebentar lagi.');

            return self::FAILURE;
        } catch (QueryException $e) {
            // A QueryException is a RuntimeException too; its message carries the SQL and the row data.
            Log::error('Radio import failed', ['exception' => $e]);
            $this->error('Terjadi kesalahan database saat impor, tidak ada data yang berubah. Detailnya ada di log aplikasi.');

            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $report['at'] = now()->format('d-m-Y H:i');
        $report['by'] = 'artisan radio:import';
        Cache::forever(RadioImportController::REPORT_CACHE_KEY, $report);

        $this->table(['Item', 'Jumlah'], [
            ['Baris dibaca', $report['rows_scanned']],
            ['Slot ID kosong dilewati', $report['empty_slots']],
            ['Radio diimpor', $report['imported']],
            ['  - aktif', $report['imported_active']],
            ['  - diarsipkan', $report['imported_archived']],
            ['Ditandai "Perlu dicek"', $report['flagged']],
            ['Baris dilewati (ada isi tapi tanpa SN/User)', count($report['skipped'])],
            ['Master baru: tipe / perusahaan / department / template', implode(' / ', $report['masters_created'])],
            ['Durasi (detik)', $report['seconds']],
        ]);

        foreach ($report['skipped'] as $skipped) {
            $this->warn("Dilewati {$skipped['sheet']} baris {$skipped['row']}: {$skipped['reason']}");
        }

        return self::SUCCESS;
    }
}
