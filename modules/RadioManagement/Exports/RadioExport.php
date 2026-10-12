<?php

namespace Modules\RadioManagement\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use Modules\RadioManagement\Models\Radio;
use Modules\RadioManagement\Services\RadioCheckService;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RadioExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithCustomValueBinder
{
    public function __construct(private Collection $radios, private RadioCheckService $checks)
    {
    }

    /**
     * Builds the workbook and returns it as a download.
     *
     * While it is written this export is PhpSpreadsheet's value binder, which is a process-wide
     * setting. It is put back afterwards: left in place, every workbook read later in the same
     * process (an import, a test) would get its numbers and dates back as text.
     */
    public static function download(Collection $radios, RadioCheckService $checks, string $fileName): BinaryFileResponse
    {
        try {
            return Excel::download(new self($radios, $checks), $fileName);
        } finally {
            Cell::setValueBinder(app(config('excel.value_binder.default', DefaultValueBinder::class)));
        }
    }

    public function collection()
    {
        return $this->radios;
    }

    /**
     * Every text stays text. Left to Excel's guessing, an MDC code such as "3E9" becomes the
     * number 3000000000, and a note starting with "=" becomes a formula. Radio IDs are integers
     * in the model, so they still export as numbers.
     */
    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function headings(): array
    {
        return [
            'Radio ID', 'MDC Code', 'SN', 'Tipe Radio', 'Kategori', 'Daya', 'Pemilik Aset', 'Owner',
            'Nama User / Unit', 'Department', 'Company', 'Template Channel', 'Tgl Program Terakhir',
            'Posisi', 'Kondisi', 'Tgl Verifikasi Terakhir', 'No. Dokumen (PO/BA)', 'Keterangan',
            'Cek', 'Diarsipkan', 'Alasan Arsip', 'Radio ID Lama',
        ];
    }

    /** @param Radio $radio */
    public function map($radio): array
    {
        return [
            $radio->dmr_id,
            $radio->mdc_code,
            $radio->serial_number,
            $radio->radioType?->name,
            $radio->radioType?->category,
            $radio->radioType?->power,
            $radio->ownerCompany?->code,
            $radio->ownerGroup(),
            $radio->user_name,
            $radio->department?->name,
            $radio->company?->code,
            $radio->channelTemplate?->name,
            $radio->last_programmed_at?->format('d-m-Y'),
            $radio->placement->label(),
            $radio->condition->label(),
            $radio->last_verified_at?->format('d-m-Y'),
            $radio->document_no,
            $radio->notes,
            collect($this->checks->issuesFor($radio))->pluck('label')->implode(', '),
            $radio->archived_at?->format('d-m-Y'),
            $radio->archive_reason,
            $radio->previous_dmr_id,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:U1')->getFont()->setBold(true);
                $sheet->freezePane('D2');
                $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            },
        ];
    }
}
