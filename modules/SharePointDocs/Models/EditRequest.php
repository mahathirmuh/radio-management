<?php

namespace Modules\SharePointDocs\Models;

use Closure;
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
 * @property bool $direct written straight away by its maker, who may approve changes too (K13h)
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

    /** The words of each status as the audit log and the API keep them (the pages say them in their language: statusName()). */
    public const LABELS = [
        self::PENDING => 'Menunggu',
        self::PROCESSING => 'Diproses',
        self::APPLIED => 'Diterapkan',
        self::REJECTED => 'Ditolak',
        self::CONFLICT => 'Konflik',
        self::FAILED => 'Gagal',
    ];

    /**
     * Why the request that is being decided now was not written (or that it was there already), in any language: set by
     * CellEdits for the answer and the notification. Null for a request read from the database, which has only what was
     * stored (in Indonesian).
     *
     * @var (Closure(string): string)|null
     */
    public ?Closure $failureWords = null;

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
        'direct' => 'boolean',
    ];

    /**
     * What the cell is to hold: the text, or "(dikosongkan)". Without $locale as the audit log and the API keep it; a page
     * gives its language.
     */
    public function newShown(?string $locale = null): string
    {
        return $this->new_kind === self::KIND_EMPTY ? self::emptyWord('cleared', '(dikosongkan)', $locale) : (string) $this->new_value;
    }

    /** What the cell held when the change was asked for: the text, or "(kosong)" (see newShown()). */
    public function oldShown(?string $locale = null): string
    {
        return (string) $this->old_text === '' ? self::emptyWord('empty', '(kosong)', $locale) : (string) $this->old_text;
    }

    /** Why it was not written (or that it was there already): in $locale when it is known in every language, else as it was stored. */
    public function failureIn(?string $locale = null): ?string
    {
        return $this->failureWords !== null ? ($this->failureWords)($locale ?? app()->getLocale()) : $this->failure;
    }

    /** The words of a status in the language of the page (or $locale). */
    public static function statusName(string $status, ?string $locale = null): string
    {
        return array_key_exists($status, self::LABELS) ? __('SharePointDocs::approvals.statuses.' . $status, [], $locale) : $status;
    }

    /** @return array<string, string> every status by its code, with its words in the language of the page */
    public static function names(): array
    {
        return array_combine(array_keys(self::LABELS), array_map(fn (string $status) => self::statusName($status), array_keys(self::LABELS)));
    }

    private static function emptyWord(string $key, string $stored, ?string $locale): string
    {
        return $locale === null ? $stored : __('SharePointDocs::approvals.' . $key, [], $locale);
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
