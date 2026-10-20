<?php

namespace Modules\RadioManagement\Services;

use Modules\RadioManagement\Enums\Condition;
use Modules\RadioManagement\Enums\Placement;
use Modules\RadioManagement\Models\ChannelTemplate;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Models\RadioCompany;
use Modules\RadioManagement\Models\RadioDepartment;
use Modules\RadioManagement\Models\RadioHistory;
use Modules\RadioManagement\Models\RadioType;
use Modules\RadioManagement\Models\ServiceTicket;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Imports the legacy "Radio Register" workbook (sheet "Form", optionally "to Konawe").
 *
 * Mechanical clean-up is automatic (spelling, spacing, status wording, Excel
 * autofill mistakes). Anything contradictory is NOT guessed: the radio is imported
 * as-is and marked "Perlu dicek" with the reason, for a human to resolve.
 */
class RadioImportService
{
    public const FORM_SHEET = 'Form';

    public const KONAWE_SHEET = 'to Konawe';

    /** Header key => accepted header texts (lower-case, "[...]" part removed). */
    private const HEADERS = [
        'no' => ['no'],
        'sn' => ['sn'],
        'radio_id' => ['radio id'],
        'user' => ['user name'],
        'department' => ['department'],
        'company' => ['company'],
        'type' => ['radio type'],
        'power' => ['radio power'],
        'channel' => ['channel list'],
        'asset' => ['device asset'],
        'last_program' => ['last program'],
        'update_status' => ['update status'],
        'status' => ['status'],
        'found' => ['found'],
        'condition' => ['condition'],
        'foto' => ['foto'],
        'remark' => ['remark'],
        'col1' => ['column1'],
        'col2' => ['column2'],
        'col3' => ['column3'],
        'col4' => ['column4'],
        'col5' => ['column5'],
    ];

    /** Company codes that may appear in "Device Asset" as the owning company. */
    private const KNOWN_COMPANIES = [
        'MTI', 'MMS', 'SJS', 'BMS', 'GLOBAL ARROW', 'SCM', 'RGD', 'ARKATO', 'IMJ', 'SUPRABAKTI', 'TRUBA',
    ];

    private const COMPANY_ALIASES = [
        'MMS(MTI)' => 'MMS',
    ];

    private const DEPARTMENT_ALIASES = [
        'CHLORIDE PLAN' => 'CHLORIDE PLANT',
        'CHOLORIDE PLANT' => 'CHLORIDE PLANT',
        'MANAGEMNET' => 'MANAGEMENT',
        'LOGISTIK' => 'LOGISTIC',
        'PYRITE' => 'PYRITE PLANT',
        'CCP' => 'CCP PLANT',
        'IT SPARE' => 'IT',
        'SUPPLY CHAIN MANAGEMENT' => 'SCM',
    ];

    private const TEMPLATE_ALIASES = [
        'GENERAL' => 'General',
        'GENARAL' => 'General',
        'GENERAL + COMMISSIONING' => 'General + Commissioning',
        'GENERAL, ERECTION PYRITE' => 'General + Erection',
        'GENERAL + ERECTION' => 'General + Erection',
        'GENERAL + ERECTION 1 DAN 2' => 'General + Erection',
        'PYRITE TEMPLATE' => 'Pyrite Template',
        'CHLORIDE PLANT' => 'Chloride Plant',
        'CHOLORIDE PLANT' => 'Chloride Plant',
        'CHLORIDE' => 'Chloride Plant',
        'CCP' => 'CCP Plant',
        'CCP PLANT' => 'CCP Plant',
        '1.EMG, 2.MAINTENACE, 3.DRIVER' => 'EMG + Maintenance + Driver',
        '1. EMG, 2. SECURITY (RPT) & 3. SECURITY (DIRECT)' => 'EMG + Security (RPT & Direct)',
        'MAINTENANCE' => 'Maintenance',
        'MAINTENACE' => 'Maintenance',
        'SJS' => 'SJS Template',
        'SJS TEMPLATE' => 'SJS Template',
        'OHS' => 'OHS Template',
        'OHS TEMPLATE' => 'OHS Template',
        'SCM' => 'SCM Template',
        'SCM TEMPLATE' => 'SCM Template',
        'TJS' => 'TJS Template',
        'TJS TEMPLATE' => 'TJS Template',
        'SJS-HAULING TEMPLATE' => 'SJS-Hauling Template',
        'BMS (15 CHANNEL)' => 'BMS (15 Channel)',
        'SECURITY' => 'Security',
        'IT' => 'IT',
    ];

    /** Default power per category, as written in the register's CODE sheet. */
    private const CATEGORY_POWER = [
        'RIG' => '5 - 30 W',
        'HT' => '5 W',
    ];

    /**
     * Column sizes, record key => [max length, Excel column name]. SQL Server refuses an
     * over-long value instead of cutting it, which would abort the whole import.
     */
    private const LIMITS = [
        'serial_number' => [50, 'SN'],
        'user_name' => [150, 'User Name'],
        'company' => [50, 'Company'],
        'owner' => [50, 'Device Asset'],
        'department' => [100, 'Department'],
        'document_no' => [100, 'No. Dokumen'],
    ];

    private const TYPE_NAME_LIMIT = 100;

    private const DAMAGE_PATTERN = '/rusak|blank|pecah|need service|perlu service|need repair/i';

    private const LOST_PATTERN = '/hilang|kehilangan/i';

    private const NOT_EMPTY = 'Data radio sudah ada. Pilih opsi "hapus data lama" untuk mengimpor ulang dari Excel.';

    /** Starting over would delete the radios that are away for repair (Servis & Perbaikan). */
    private const OPEN_TICKETS = 'Masih ada %d tiket servis yang terbuka. Impor yang menghapus data lama baru bisa dijalankan setelah semua radio itu kembali (menu Servis & Perbaikan).';

    /** Upper bound for the write phase; the web request allows itself 600 s as well. */
    private const WRITE_SECONDS = 600;

    public function __construct(private RegisterLock $lock)
    {
    }

    /**
     * @param array{fresh?: bool, konawe?: bool, user_id?: ?int, file_name?: string} $options
     */
    public function import(string $path, array $options = []): array
    {
        $started = microtime(true);
        $fresh = (bool) ($options['fresh'] ?? false);
        $withKonawe = (bool) ($options['konawe'] ?? true);
        $userId = $options['user_id'] ?? null;
        $fileName = $options['file_name'] ?? basename($path);

        // Refuse early, before the workbook is read. Checked again where it counts, below.
        if (! $fresh && Radio::withTrashed()->exists()) {
            throw new RuntimeException(self::NOT_EMPTY);
        }
        if ($fresh && ($open = ServiceTicket::openCount()) > 0) {
            throw new RuntimeException(sprintf(self::OPEN_TICKETS, $open));
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $sheetNames = $reader->listWorksheetNames($path);
        if (! in_array(self::FORM_SHEET, $sheetNames, true)) {
            throw new RuntimeException("Sheet \"" . self::FORM_SHEET . "\" tidak ditemukan di file ini.");
        }
        $withKonawe = $withKonawe && in_array(self::KONAWE_SHEET, $sheetNames, true);
        $reader->setLoadSheetsOnly($withKonawe ? [self::FORM_SHEET, self::KONAWE_SHEET] : [self::FORM_SHEET]);
        $book = $reader->load($path);

        $report = [
            'file' => $fileName,
            'sheets' => $withKonawe ? [self::FORM_SHEET, self::KONAWE_SHEET] : [self::FORM_SHEET],
            'rows_scanned' => 0,
            'empty_slots' => 0,
            'imported' => 0,
            'imported_active' => 0,
            'imported_archived' => 0,
            'flagged' => 0,
            'skipped' => [],
            'flags' => [],
            'masters_created' => ['types' => 0, 'companies' => 0, 'departments' => 0, 'templates' => 0],
            'fresh' => $fresh,
            'seconds' => 0,
        ];

        $records = $this->parseForm($book->getSheetByName(self::FORM_SHEET), $report);
        if ($withKonawe) {
            $this->parseKonawe($book->getSheetByName(self::KONAWE_SHEET), $records, $report);
        }
        $book->disconnectWorksheets();
        unset($book);

        $this->fitColumns($records);
        $this->flagDuplicates($records);

        // The write is one long turn on the register: nobody saves a radio while its rows are
        // being replaced, and a second import that was started meanwhile finds it no longer empty.
        $this->lock->run(function () use ($records, $fresh, $userId, $fileName, &$report) {
            if ($fresh) {
                if (($open = ServiceTicket::openCount()) > 0) {
                    throw new RuntimeException(sprintf(self::OPEN_TICKETS, $open));
                }
                RadioHistory::query()->delete();
                Radio::withTrashed()->forceDelete();
            } elseif (Radio::withTrashed()->exists()) {
                throw new RuntimeException(self::NOT_EMPTY);
            }
            $this->write($records, $userId, $fileName, $report);
        }, self::WRITE_SECONDS);

        $report['seconds'] = round(microtime(true) - $started, 1);

        return $report;
    }

    // ------------------------------------------------------------------ parsing

    private function parseForm(Worksheet $sheet, array &$report): array
    {
        $columns = $this->headerColumns($sheet);
        foreach (['sn', 'user', 'radio_id'] as $required) {
            if (! isset($columns[$required])) {
                throw new RuntimeException("Kolom \"{$required}\" tidak ditemukan di sheet " . self::FORM_SHEET . '.');
            }
        }

        $pending = [];
        $lastRow = $sheet->getHighestDataRow();
        $slotOffset = null; // Radio ID - No in the slot-numbered main block (10, later 9)

        for ($row = 2; $row <= $lastRow; $row++) {
            $cells = $this->readRow($sheet, $columns, $row);
            $values = array_filter(array_diff_key($cells, ['__source' => true]), fn ($value) => $value !== null);
            if (! $values) {
                continue;
            }
            $report['rows_scanned']++;

            // Each main-block row is the slot of one Radio ID. When the ID cell is blank or
            // garbage, the slot position still tells which ID the radio most likely carries.
            $no = $values['no'] ?? null;
            [$rowId] = $this->radioId($cells['radio_id'] ?? null);
            $slotId = null;
            if (is_numeric($no) && (int) $no < 10000) {
                if ($rowId) {
                    $slotOffset = $rowId - (int) $no;
                } elseif ($slotOffset !== null) {
                    $slotId = (int) $no + $slotOffset;
                }
            }

            $sn = $this->serial($cells['sn'] ?? null);
            $user = $this->text($cells['user'] ?? null);

            if (! $sn['value'] && ! $user) {
                // Rows holding only No + Radio ID are the pre-numbered empty ID slots.
                $leftover = array_diff_key($values, array_flip(['no', 'radio_id']));
                if ($leftover) {
                    $report['skipped'][] = [
                        'sheet' => self::FORM_SHEET,
                        'row' => $row,
                        'reason' => 'Tidak ada SN maupun User Name; isi lain: ' . $this->summarize($leftover),
                    ];
                } else {
                    $report['empty_slots']++;
                }
                continue;
            }

            $pending[] = [$row, $cells, $sn, $user, $slotId];
        }

        // Departments used anywhere in the sheet tell real names ("IT") apart from junk in Device Asset.
        $knownDepartments = collect($pending)
            ->map(fn (array $item) => $this->department($item[1]['department'] ?? null)[0])
            ->filter()
            ->unique()
            ->all();

        return array_map(
            fn (array $item) => $this->buildFormRecord(...$item, knownDepartments: $knownDepartments),
            $pending
        );
    }

    private function buildFormRecord(int $row, array $cells, array $sn, ?string $user, ?int $slotId, array $knownDepartments): array
    {
        $flags = [];
        $notes = [];

        // No = 12345 / 232121 / 11111 marks the unstructured block under the main table.
        $no = $cells['no'] ?? null;
        if (! is_numeric($no) || (int) $no >= 10000) {
            $flags[] = 'Baris berasal dari blok bawah Excel (No = ' . ($no ?? 'kosong') . '), cek apakah unit ini masih valid.';
        }

        if ($sn['extra']) {
            $flags[] = "Sel SN berisi lebih dari satu nomor: {$sn['raw']}.";
        }

        [$dmrId, $badId] = $this->radioId($cells['radio_id'] ?? null);
        $problem = $badId !== null ? "tidak valid (\"{$badId}\")" : 'kosong';
        if (! $dmrId && $slotId) {
            // Not assigned automatically: kept as the last known ID, which also keeps it
            // out of the "next free ID" suggestions until someone checks the radio.
            $flags[] = "Radio ID di Excel {$problem}; menurut urutan slot Excel kemungkinan {$slotId} (MDC "
                . RadioIdService::mdc($slotId) . '). Cek di radio, lalu isi Radio ID.';
        } elseif ($badId !== null) {
            $flags[] = "Radio ID di Excel {$problem}.";
        }

        [$type, $typeNote] = $this->radioType($cells['type'] ?? null);
        if ($typeNote) {
            $notes[] = $typeNote;
        }
        $excelPower = $this->text($cells['power'] ?? null);
        if ($type && $excelPower && $this->powerKey($excelPower) !== $this->powerKey($type['power'])) {
            $flags[] = "Daya di Excel \"{$excelPower}\" tidak sesuai tipe {$type['name']} ({$type['power']}).";
        }

        $company = $this->companyCode($cells['company'] ?? null);
        [$owner, $ownerFlag] = $this->owner($cells['asset'] ?? null, $company, $knownDepartments);
        if ($ownerFlag) {
            $flags[] = $ownerFlag;
        }

        [$department, $departmentFlag] = $this->department($cells['department'] ?? null);
        if ($departmentFlag) {
            $flags[] = $departmentFlag;
        }

        [$template, $templateNote] = $this->template($cells['channel'] ?? null);
        if ($templateNote) {
            $notes[] = $templateNote;
        }

        // Last Program holds either a date or a free-text handover note.
        $lastProgramRaw = $cells['last_program'] ?? null;
        $lastProgrammed = $this->date($lastProgramRaw);
        if (! $lastProgrammed && $lastProgramRaw !== null) {
            $notes[] = 'Last Program: ' . $lastProgramRaw;
        }

        // Update Status holds "Y" or the verification date.
        $verified = $this->date($cells['update_status'] ?? null);

        $status = $this->status($cells['status'] ?? null);
        $flags = array_merge($flags, $status['flags']);
        if ($status['note']) {
            $notes[] = $status['note'];
        }
        if ($status['date'] && ! $verified) {
            $verified = $status['date'];
        }

        $freeTexts = array_filter([
            'Foto' => $this->text($cells['foto'] ?? null),
            'Remark' => $this->text($cells['remark'] ?? null),
            'Column1' => $this->text($this->dateOrText($cells['col1'] ?? null)),
            'Column2' => $this->text($cells['col2'] ?? null),
            'Column3' => $this->text($cells['col3'] ?? null),
            'Column4' => $this->text($cells['col4'] ?? null),
            'Column5' => $this->text($cells['col5'] ?? null),
        ]);
        foreach ($freeTexts as $text) {
            $notes[] = $text;
        }
        $documentNo = $this->documentNo($freeTexts);

        $placement = $status['placement'] ?? $this->placementFromText($user, $freeTexts);
        $condition = $status['condition'];

        $context = implode(' | ', array_filter(array_merge([$user, is_string($lastProgramRaw) ? $lastProgramRaw : null], $freeTexts)));
        $statusLabel = $status['raw'] ?? 'kosong';
        if ($condition === Condition::Layak->value && preg_match(self::DAMAGE_PATTERN, $context)) {
            $flags[] = "Catatan menyebut kerusakan/service, tapi status Excel \"{$statusLabel}\": " . $this->excerpt($context, self::DAMAGE_PATTERN);
        }
        if ($placement !== Placement::Hilang->value && preg_match(self::LOST_PATTERN, $context)) {
            $flags[] = "Catatan menyebut \"hilang\", tapi status Excel \"{$statusLabel}\": " . $this->excerpt($context, self::LOST_PATTERN);
        }

        return [
            'sheet' => self::FORM_SHEET,
            'row' => $row,
            'dmr_id' => $dmrId,
            'serial_number' => $sn['value'],
            'type' => $type,
            'owner' => $owner,
            'user_name' => $user,
            'department' => $department,
            'company' => $company,
            'template' => $template,
            'last_programmed_at' => $lastProgrammed,
            'placement' => $placement,
            'condition' => $condition,
            'last_verified_at' => $verified,
            'document_no' => $documentNo,
            'notes' => $notes,
            'flags' => $flags,
            'archived_at' => null,
            'archive_reason' => null,
            'previous_dmr_id' => $dmrId ? null : $slotId,
            'source' => $cells['__source'],
        ];
    }

    /**
     * "to Konawe": radios transferred to the Konawe site (archived here as
     * "Pindah Site") and a few that stayed "At IT" without being in the Form.
     */
    private function parseKonawe(Worksheet $sheet, array &$records, array &$report): void
    {
        $columns = $this->headerColumns($sheet);
        if (! isset($columns['sn'])) {
            return;
        }

        $bySerial = [];
        $activeIds = [];
        foreach ($records as $index => $record) {
            if ($record['serial_number']) {
                $bySerial[$record['serial_number']] = $index;
            }
            if ($record['dmr_id']) {
                $activeIds[$record['dmr_id']] = $record['serial_number'] ?? ('baris ' . $record['row']);
            }
        }

        $lastRow = $sheet->getHighestDataRow();
        for ($row = 2; $row <= $lastRow; $row++) {
            $cells = $this->readRow($sheet, $columns, $row);
            $sn = $this->serial($cells['sn'] ?? null);
            if (! $sn['value']) {
                continue;
            }
            $report['rows_scanned']++;

            $remark = implode(' | ', array_filter([
                $this->text($cells['remark'] ?? null),
                $this->text($cells['col1'] ?? null),
                $this->text($cells['col2'] ?? null),
            ]));

            if (isset($bySerial[$sn['value']])) {
                $records[$bySerial[$sn['value']]]['notes'][] = "Juga tercatat di sheet \"to Konawe\": {$remark}";
                continue;
            }

            [$type] = $this->radioType($cells['type'] ?? null);
            [$department] = $this->department($cells['department'] ?? null);
            [$template, $templateNote] = $this->template($cells['channel'] ?? null);
            [$sheetId] = $this->radioId($cells['radio_id'] ?? null);
            $company = $this->companyCode($cells['company'] ?? null);
            [$owner] = $this->owner($cells['asset'] ?? null, $company);
            $condition = strcasecmp((string) ($cells['condition'] ?? ''), 'Layak') === 0
                ? Condition::Layak->value
                : Condition::BelumDicek->value;

            $record = [
                'sheet' => self::KONAWE_SHEET,
                'row' => $row,
                'dmr_id' => null,
                'serial_number' => $sn['value'],
                'type' => $type,
                'owner' => $owner,
                'user_name' => $this->text($cells['user'] ?? null),
                'department' => $department,
                'company' => $company,
                'template' => $template,
                'last_programmed_at' => $this->date($cells['last_program'] ?? null),
                'placement' => Placement::DiUser->value,
                'condition' => $condition,
                'last_verified_at' => null,
                'document_no' => null,
                'notes' => array_values(array_filter(["Sheet \"to Konawe\": {$remark}", $templateNote])),
                'flags' => [],
                'archived_at' => null,
                'archive_reason' => null,
                'previous_dmr_id' => null,
                'source' => $cells['__source'],
            ];

            if (preg_match('/konawe/i', $remark)) {
                $record['archived_at'] = $this->firstDateIn($remark) ?? now()->toDateString();
                $record['archive_reason'] = 'Pindah Site';
                $record['previous_dmr_id'] = $sheetId;
            } else {
                // Still at IT but missing from the Form: its old ID may belong to another radio now.
                $record['placement'] = Placement::StokIt->value;
                if ($sheetId && isset($activeIds[$sheetId])) {
                    $record['previous_dmr_id'] = $sheetId;
                    $record['flags'][] = "Tercatat \"{$remark}\" di sheet \"to Konawe\" tetapi tidak ada di Form. "
                        . "Radio ID lama {$sheetId} sekarang dipakai {$activeIds[$sheetId]}: cek fisik dan program ulang.";
                } else {
                    $record['dmr_id'] = $sheetId;
                    $record['flags'][] = "Tercatat \"{$remark}\" di sheet \"to Konawe\" tetapi tidak ada di Form.";
                }
            }

            $records[] = $record;
        }
    }

    /** "Flag, do not fail": a value too long for its column is cut and the radio marked "Perlu dicek". */
    private function fitColumns(array &$records): void
    {
        foreach ($records as &$record) {
            foreach (self::LIMITS as $key => [$max, $label]) {
                $record[$key] = $this->fit($record[$key], $max, $label, $record['flags']);
            }
            if ($record['type']) {
                $record['type']['name'] = $this->fit($record['type']['name'], self::TYPE_NAME_LIMIT, 'Radio Type', $record['flags']);
            }
        }
    }

    private function fit(?string $value, int $max, string $label, array &$flags): ?string
    {
        if ($value === null || mb_strlen($value) <= $max) {
            return $value;
        }
        $flags[] = "{$label} di Excel lebih panjang dari {$max} karakter, dipotong. Isi lengkapnya ada di \"Data Asli Excel\".";

        return mb_substr($value, 0, $max);
    }

    private function flagDuplicates(array &$records): void
    {
        $bySerial = [];
        $byId = [];
        foreach ($records as $index => $record) {
            if ($record['serial_number']) {
                $bySerial[$record['serial_number']][] = $index;
            }
            if ($record['dmr_id'] && ! $record['archived_at']) {
                $byId[$record['dmr_id']][] = $index;
            }
        }

        foreach ([['SN', $bySerial], ['Radio ID', $byId]] as [$label, $groups]) {
            foreach ($groups as $value => $indexes) {
                if (count($indexes) < 2) {
                    continue;
                }
                foreach ($indexes as $index) {
                    $others = collect($indexes)
                        ->reject(fn ($other) => $other === $index)
                        ->map(fn ($other) => $this->rowLabel($records[$other]))
                        ->implode(', ');
                    $records[$index]['flags'][] = "{$label} {$value} dobel dengan {$others}.";
                }
            }
        }
    }

    // ------------------------------------------------------------------ writing

    private function write(array $records, ?int $userId, string $fileName, array &$report): void
    {
        $types = [];
        $companies = [];
        $departments = [];
        $templates = [];
        $today = now()->toDateString();
        $timestamp = now();
        $histories = [];

        foreach ($records as $record) {
            $typeId = $record['type'] ? $this->master($types, 'types', $report, fn () => RadioType::firstOrCreate(
                ['name' => $record['type']['name']],
                ['category' => $record['type']['category'], 'power' => $record['type']['power']]
            ), $record['type']['name']) : null;
            $ownerId = $record['owner'] ? $this->master($companies, 'companies', $report, fn () => RadioCompany::firstOrCreate(
                ['code' => $record['owner']]
            ), $record['owner']) : null;
            $companyId = $record['company'] ? $this->master($companies, 'companies', $report, fn () => RadioCompany::firstOrCreate(
                ['code' => $record['company']]
            ), $record['company']) : null;
            $departmentId = $record['department'] ? $this->master($departments, 'departments', $report, fn () => RadioDepartment::firstOrCreate(
                ['name' => $record['department']]
            ), $record['department']) : null;
            $templateId = $record['template'] ? $this->master($templates, 'templates', $report, fn () => ChannelTemplate::firstOrCreate(
                ['name' => $record['template']]
            ), $record['template']) : null;

            $flags = array_values(array_unique($record['flags']));

            $radio = Radio::create([
                'dmr_id' => $record['dmr_id'],
                'serial_number' => $record['serial_number'],
                'radio_type_id' => $typeId,
                'owner_company_id' => $ownerId,
                'user_name' => $record['user_name'],
                'department_id' => $departmentId,
                'company_id' => $companyId,
                'channel_template_id' => $templateId,
                'last_programmed_at' => $record['last_programmed_at'],
                'placement' => $record['placement'],
                'condition' => $record['condition'],
                'last_verified_at' => $record['last_verified_at'],
                'document_no' => $record['document_no'],
                'notes' => $record['notes'] ? implode("\n", array_unique($record['notes'])) : null,
                'needs_review' => (bool) $flags,
                'review_notes' => $flags ? implode("\n", $flags) : null,
                'archived_at' => $record['archived_at'],
                'archive_reason' => $record['archive_reason'],
                'previous_dmr_id' => $record['previous_dmr_id'],
                'source_row' => $record['row'],
                'source_sheet' => $record['sheet'],
                'source_data' => $record['source'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $histories[] = [
                'radio_id' => $radio->id,
                'event_date' => $today,
                'action' => 'Impor',
                'description' => "Diimpor dari \"{$fileName}\", sheet {$record['sheet']} baris {$record['row']}.",
                'user_id' => $userId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
            if ($record['archived_at']) {
                $histories[] = [
                    'radio_id' => $radio->id,
                    'event_date' => $record['archived_at'],
                    'action' => 'Arsip',
                    'description' => "Diarsipkan: {$record['archive_reason']}."
                        . ($record['previous_dmr_id'] ? " Radio ID {$record['previous_dmr_id']} dilepas." : ''),
                    'user_id' => $userId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            $report['imported']++;
            $record['archived_at'] ? $report['imported_archived']++ : $report['imported_active']++;
            if ($flags) {
                $report['flagged']++;
                $report['flags'][] = [
                    'sheet' => $record['sheet'],
                    'row' => $record['row'],
                    'radio' => $radio->id,
                    'sn' => $record['serial_number'],
                    'dmr_id' => $record['dmr_id'],
                    'reasons' => $flags,
                ];
            }
        }

        // 7 columns x 200 rows stays well under SQL Server's 2100-parameter limit.
        foreach (array_chunk($histories, 200) as $chunk) {
            RadioHistory::insert($chunk);
        }
    }

    private function master(array &$cache, string $kind, array &$report, callable $resolve, string $key): int
    {
        if (! isset($cache[$key])) {
            $model = $resolve();
            if ($model->wasRecentlyCreated) {
                $report['masters_created'][$kind]++;
            }
            $cache[$key] = $model->id;
        }

        return $cache[$key];
    }

    // ------------------------------------------------------------------ cells

    /** @return array<string, int> header key => column index */
    private function headerColumns(Worksheet $sheet): array
    {
        $columns = [];
        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($column = 1; $column <= $lastColumn; $column++) {
            $header = $this->cellValue($sheet, $column, 1);
            if (! is_string($header)) {
                continue;
            }
            $normalized = trim(mb_strtolower(preg_replace('/\[.*?\]/', '', $header)));
            foreach (self::HEADERS as $key => $accepted) {
                if (! isset($columns[$key]) && in_array($normalized, $accepted, true)) {
                    $columns[$key] = $column;
                    $columns['__header'][$column] = trim($header);
                }
            }
        }

        return $columns;
    }

    private function readRow(Worksheet $sheet, array $columns, int $row): array
    {
        $cells = [];
        $source = [];
        foreach ($columns as $key => $column) {
            if ($key === '__header') {
                continue;
            }
            $value = $this->cellValue($sheet, $column, $row);
            $cells[$key] = $value;
            if ($value !== null) {
                $source[$columns['__header'][$column]] = $this->sourceValue($key, $value);
            }
        }
        $cells['__source'] = $source;

        return $cells;
    }

    private function cellValue(Worksheet $sheet, int $column, int $row): mixed
    {
        $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
        if (! $sheet->cellExists($coordinate)) {
            return null;
        }
        $cell = $sheet->getCell($coordinate);
        $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
        if ($value instanceof RichText) {
            $value = $value->getPlainText();
        }
        if (is_string($value)) {
            $value = trim(preg_replace('/\s+/u', ' ', $value));
            // Empty cells and Excel error results such as #VALUE! carry no data.
            if ($value === '' || preg_match('~^#(VALUE!|N/A|REF!|DIV/0!|NAME\?|NUM!|NULL!)$~', $value)) {
                return null;
            }
        }

        return $value;
    }

    /** Keep the original value for the "Data asli Excel" panel, with dates made readable. */
    private function sourceValue(string $key, mixed $value): mixed
    {
        if (in_array($key, ['last_program', 'update_status', 'status', 'col1'], true) && ($date = $this->date($value))) {
            return is_numeric($value) ? $date : $value;
        }

        return is_float($value) && floor($value) === $value ? (int) $value : $value;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $text === '' ? null : $text;
    }

    private function dateOrText(mixed $value): mixed
    {
        return is_numeric($value) && ($date = $this->date($value)) ? $date : $value;
    }

    /** Excel serial number or d/m/y text => Y-m-d. */
    private function date(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            // 20000..80000 is 1954..2119: anything else is not a date serial.
            return $value >= 20000 && $value <= 80000
                ? ExcelDate::excelToDateTimeObject($value)->format('Y-m-d')
                : null;
        }
        if (is_string($value) && preg_match('~^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2}|\d{4})$~', trim($value), $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $year += $year < 100 ? 2000 : 0;

            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        }

        return null;
    }

    private function firstDateIn(string $text): ?string
    {
        return preg_match('~\b(\d{1,2}[/\-.]\d{1,2}[/\-.](?:\d{4}|\d{2}))\b~', $text, $m) ? $this->date($m[1]) : null;
    }

    /** @return array{value: ?string, raw: ?string, extra: bool} */
    private function serial(mixed $value): array
    {
        if ($value === null) {
            return ['value' => null, 'raw' => null, 'extra' => false];
        }
        $raw = is_float($value) ? sprintf('%.0f', $value) : trim((string) $value);
        $parts = array_values(array_filter(array_map('trim', preg_split('~[/,;]~', $raw))));
        $serial = $parts ? mb_strtoupper(preg_replace('/\s+/', '', $parts[0])) : null;

        return ['value' => $serial ?: null, 'raw' => $raw, 'extra' => count($parts) > 1];
    }

    /** @return array{0: ?int, 1: ?string} [id, invalid raw text] */
    private function radioId(mixed $value): array
    {
        if ($value === null) {
            return [null, null];
        }
        // Above the highest Radio ID a radio can carry it is not an ID (e.g. a serial number
        // typed into the wrong column), and it would not fit the database column either.
        $max = (int) config('radio.id_max');
        if ((is_int($value) || is_float($value)) && floor($value) == $value && $value > 0 && $value <= $max) {
            return [(int) $value, null];
        }
        if (is_string($value) && ctype_digit($value) && strlen($value) <= 9 && (int) $value > 0 && (int) $value <= $max) {
            return [(int) $value, null];
        }

        // Shown in the "Perlu dicek" note exactly as the sheet has it: 8675310999, 601.5, "12A".
        $whole = is_float($value) && floor($value) == $value;

        return [null, $whole ? sprintf('%.0f', $value) : (string) $value];
    }

    /** @return array{0: ?array{name: string, category: string, power: string}, 1: ?string} */
    private function radioType(mixed $value): array
    {
        $name = $this->text($value);
        if (! $name) {
            return [null, null];
        }
        $note = null;
        // Dragging an M3688 cell down in Excel produced M3689, M3690, ...
        if (preg_match('/^(MotoTrbo XiR )M(3689|369\d)$/i', $name, $m)) {
            $note = "Tipe di Excel \"{$name}\" dikoreksi ke M3688.";
            $name = $m[1] . 'M3688';
        }
        $category = preg_match('/\bM\d{4}\b/i', $name) ? 'RIG' : 'HT';

        return [['name' => $name, 'category' => $category, 'power' => self::CATEGORY_POWER[$category]], $note];
    }

    private function powerKey(string $power): string
    {
        return mb_strtolower(preg_replace('/\s+/', '', $power));
    }

    private function companyCode(mixed $value): ?string
    {
        $code = $this->text($value);
        if (! $code) {
            return null;
        }
        $code = mb_strtoupper($code);

        return self::COMPANY_ALIASES[$code] ?? $code;
    }

    /**
     * "Device Asset" is mostly the owning company; for ~200 MTI rows it holds the
     * department instead, in which case the user's company is the owner.
     *
     * @return array{0: ?string, 1: ?string} [owner code, flag]
     */
    private function owner(mixed $value, ?string $company, array $knownDepartments = []): array
    {
        $asset = $this->companyCode($value);
        if (! $asset) {
            return [null, null];
        }
        if (in_array($asset, self::KNOWN_COMPANIES, true)) {
            return [$asset, null];
        }
        $asDepartment = self::DEPARTMENT_ALIASES[$asset] ?? $asset;
        if (mb_strlen($asset) <= 2 && ! in_array($asDepartment, $knownDepartments, true)) {
            return [$company, "Device Asset di Excel tidak dikenal: \"{$value}\"."];
        }

        return [$company, null];
    }

    /** @return array{0: ?string, 1: ?string} [department, flag] */
    private function department(mixed $value): array
    {
        $name = $this->text($value);
        if (! $name) {
            return [null, null];
        }
        $name = mb_strtoupper($name);
        if (mb_strlen($name) < 2 || ! preg_match('/[A-Z]/', $name)) {
            return [null, "Department di Excel tidak valid: \"{$value}\"."];
        }

        return [self::DEPARTMENT_ALIASES[$name] ?? $name, null];
    }

    /** @return array{0: ?string, 1: ?string} [template, note] */
    private function template(mixed $value): array
    {
        $text = $this->text($value);
        if (! $text) {
            return [null, null];
        }
        $key = mb_strtoupper($text);
        if (isset(self::TEMPLATE_ALIASES[$key])) {
            return [self::TEMPLATE_ALIASES[$key], null];
        }
        if (mb_strlen($text) > 40) {
            return ['Custom', "Channel list di Excel: {$text}"];
        }

        return [$text, null];
    }

    /**
     * Excel "Status" mixed condition (Layak/Rusak) with presence (Available/Lost),
     * plus a few dates and free texts. Split it into placement + condition.
     *
     * @return array{raw: ?string, placement: ?string, condition: string, note: ?string, date: ?string, flags: string[]}
     */
    private function status(mixed $value): array
    {
        $result = ['raw' => null, 'placement' => null, 'condition' => Condition::BelumDicek->value, 'note' => null, 'date' => null, 'flags' => []];
        if ($value === null) {
            return $result;
        }

        if (is_int($value) || is_float($value)) {
            $result['date'] = $this->date($value);
            $result['raw'] = $result['date'] ?? (string) $value;
            $result['flags'][] = "Kolom Status di Excel berisi tanggal ({$result['raw']}); dipakai sebagai tanggal verifikasi.";

            return $result;
        }

        $raw = (string) $value;
        $result['raw'] = $raw;
        $key = mb_strtolower(preg_replace('/\s+/', '', $raw));

        // In the Sep 2024 audit "Available" meant found and fine; damaged units were marked "rusak".
        if (str_starts_with($key, 'layak') || in_array($key, ['available', 'availabe', 'avalaible', 'new', 'baru', 'htbekas'], true)) {
            $result['condition'] = Condition::Layak->value;
        } elseif ($key === 'rusak') {
            $result['condition'] = Condition::Rusak->value;
        } elseif (in_array($key, ['needrepair', 'tidakbisadisetting'], true)) {
            $result['condition'] = Condition::PerluService->value;
        } elseif ($key === 'lost') {
            $result['placement'] = Placement::Hilang->value;
        } elseif (str_contains($key, 'dikembalikankeit')) {
            $result['placement'] = Placement::StokIt->value;
            $result['flags'][] = "Status Excel \"{$raw}\": dipindah ke Stok IT, kondisi belum dicek.";
        } else {
            $result['flags'][] = "Status Excel tidak standar: \"{$raw}\".";
        }

        if (! in_array($key, ['layak', 'available'], true)) {
            $result['note'] = "Status Excel: {$raw}";
        }

        return $result;
    }

    private function placementFromText(?string $user, array $texts): string
    {
        if ($user && (preg_match('/\bspare\b/i', $user) || mb_strtoupper(trim($user)) === 'IT')) {
            return Placement::StokIt->value;
        }
        foreach ($texts as $text) {
            if (preg_match('/\bat it\b/i', $text)) {
                return Placement::StokIt->value;
            }
        }

        return Placement::DiUser->value;
    }

    private function documentNo(array $texts): ?string
    {
        foreach ($texts as $text) {
            if (preg_match('/\b(PO|BA)\s*-?\s*(\d{3,})\b/i', $text, $m)) {
                return mb_strtoupper($m[1]) . ' ' . $m[2];
            }
        }

        return null;
    }

    private function excerpt(string $text, string $pattern): string
    {
        if (! preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $start = max(0, $m[0][1] - 40);

        return '"' . trim(($start > 0 ? '…' : '') . mb_substr($text, $start, 90)) . '"';
    }

    private function summarize(array $cells): string
    {
        return collect($cells)
            ->map(fn ($value, $key) => $key . '=' . (is_scalar($value) ? $value : json_encode($value)))
            ->implode(', ');
    }

    private function rowLabel(array $record): string
    {
        return $record['sheet'] === self::FORM_SHEET
            ? "baris Excel {$record['row']}"
            : "sheet \"{$record['sheet']}\" baris {$record['row']}";
    }
}
