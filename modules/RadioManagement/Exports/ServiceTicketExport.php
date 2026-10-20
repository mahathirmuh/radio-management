<?php

namespace Modules\RadioManagement\Exports;

use Illuminate\Support\Carbon;
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
use Modules\RadioManagement\Models\ServiceTicket;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The service tickets as a workbook (the button Export of "Servis & Perbaikan"): open and closed ones, newest first. */
class ServiceTicketExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithCustomValueBinder
{
    private Carbon $today;

    public function __construct(private Collection $tickets)
    {
        $this->today = now()->startOfDay();
    }

    /**
     * Builds the workbook and returns it as a download. The value binder is a process-wide setting while it is written:
     * it is put back afterwards (RadioExport says why).
     */
    public static function download(Collection $tickets, string $fileName): BinaryFileResponse
    {
        try {
            return Excel::download(new self($tickets), $fileName);
        } finally {
            Cell::setValueBinder(app(config('excel.value_binder.default', DefaultValueBinder::class)));
        }
    }

    public function collection()
    {
        return $this->tickets;
    }

    /** Every text stays text (an SN, a BA, an issue that starts with "="). Amounts and days are numbers. */
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
            'No. Tiket', 'Radio ID', 'SN', 'Tipe Radio', 'Masalah', 'Dilaporkan oleh', 'Status', 'Tgl Lapor',
            'Pusat Servis', 'Tgl Kirim', 'No. BA', 'Perkiraan Kembali', 'Tgl Kembali', 'Hari di Servis', 'Penawaran (Rp)', 'Biaya (Rp)',
        ];
    }

    /** @param ServiceTicket $ticket */
    public function map($ticket): array
    {
        return [
            $ticket->number,
            $ticket->radio_dmr_id,
            $ticket->radio_sn,
            $ticket->radio_type,
            $ticket->issue,
            $ticket->reported_by,
            $ticket->label(),
            $ticket->created_at?->format('d-m-Y'),
            $ticket->service_center,
            $ticket->sent_at?->format('d-m-Y'),
            $ticket->document_no,
            $ticket->expected_at?->format('d-m-Y'),
            $ticket->returned_at?->format('d-m-Y'),
            $ticket->sent_at ? $ticket->daysAway($this->today) : null,
            $ticket->quote,
            $ticket->cost,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:P1')->getFont()->setBold(true);
                $sheet->freezePane('B2');
                $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            },
        ];
    }
}
