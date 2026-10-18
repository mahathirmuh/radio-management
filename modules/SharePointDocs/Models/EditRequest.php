<?php

namespace Modules\SharePointDocs\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A request to change one cell of a SharePoint workbook, and what became of it (see CellEdits).
 *
 * @property int $id
 * @property string $workbook
 * @property string $sheet
 * @property string $cell
 * @property int $line
 * @property string $status
 * @property int $requested_by
 */
class EditRequest extends Model
{
    public const PENDING = 'pending';

    /** Somebody is writing it to SharePoint now. */
    public const PROCESSING = 'processing';

    public const APPLIED = 'applied';

    public const REJECTED = 'rejected';

    /** The cell, or its row, was not what it was when the change was asked for: nothing was written. */
    public const CONFLICT = 'conflict';

    /** SharePoint could not be written to now: nothing was written, and it can be tried again. */
    public const FAILED = 'failed';

    public const KIND_TEXT = 'text';

    public const KIND_NUMBER = 'number';

    public const KIND_EMPTY = 'empty';

    /** The words of each status, as the pages say them. */
    public const LABELS = [
        self::PENDING => 'Menunggu',
        self::PROCESSING => 'Diproses',
        self::APPLIED => 'Diterapkan',
        self::REJECTED => 'Ditolak',
        self::CONFLICT => 'Konflik',
        self::FAILED => 'Gagal',
    ];

    protected $table = 'sharepoint_edit_requests';

    protected $guarded = [];

    protected $casts = [
        'line' => 'integer',
        'old_number' => 'float',
        'new_number' => 'float',
        'requested_by' => 'integer',
        'decided_by' => 'integer',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    /** What the cell is to hold, as the pages show it: the text, or "(dikosongkan)". */
    public function newShown(): string
    {
        return $this->new_kind === self::KIND_EMPTY ? '(dikosongkan)' : (string) $this->new_value;
    }

    public function oldShown(): string
    {
        return (string) $this->old_text === '' ? '(kosong)' : (string) $this->old_text;
    }

    /** The cell it is about, as the audit trail names it: "ICT Masterlist · COF!B12". */
    public function auditLabel(): string
    {
        return config("sharepoint-docs.workbooks.{$this->workbook}.title", $this->workbook) . ' · ' . $this->sheet . '!' . $this->cell;
    }

    /** The cell it is about, as a notification says it: "Kartu HALO F4 (Limit, ICT/BAK/002)". */
    public function placeShown(): string
    {
        $about = array_values(array_filter([(string) $this->column_title, (string) $this->anchor_value], fn (string $part) => $part !== ''));

        return "{$this->sheet} {$this->cell}" . ($about === [] ? '' : ' (' . implode(', ', $about) . ')');
    }

    /** What the search of the page "Persetujuan Edit" finds this request by: its document number (or its sheet), and its cell. */
    public function searchTerms(): string
    {
        $where = (string) $this->anchor_value !== '' ? mb_substr((string) $this->anchor_value, 0, 60) : (string) $this->sheet;

        return trim("{$where} {$this->cell}");
    }
}
