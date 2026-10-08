<?php

namespace Modules\SharePointDocs\Services;

use DateTimeZone;
use Generator;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * The cells of an Excel workbook (.xlsx), read straight from the XML inside the file: the names
 * of the sheets, the value of each cell, the number format of a number, and whether a cell is a
 * formula. Nothing else of the workbook is looked at (styles, conditional formatting, validation,
 * drawings), which is why a register of a thousand rows takes a fraction of a second here and
 * several seconds when the whole workbook is built up in memory.
 *
 * Formulas are not worked out: a formula cell has the value Excel stored with it when the workbook
 * was last saved.
 *
 * A workbook is a file anybody who may save it in SharePoint can make, so the reading is bounded:
 * by what the archive says it inflates to, by the number of cells and bytes of text that are kept,
 * by the cells that are merely walked over, by the memory it may take and by the time it may
 * take. A part that has a document type declaration (which no workbook has, and which is how XML
 * makes itself bigger than the file) is refused.
 *
 * (The limits that a test has to be able to lower are protected constants, read as static::NAME.)
 */
class XlsxReader
{
    /** The default for the cells that hold a value (over all sheets) a workbook may have before it is refused. */
    public const MAX_CELLS = 200_000;

    /** What the parts of the archive may inflate to, in all, by what the archive declares. */
    private const MAX_INFLATED_BYTES = 256 * 1024 * 1024;

    /** Cells (<c> elements) walked over, whether they hold anything or only a format. */
    protected const MAX_WALKED = 5_000_000;

    /** Sheets a workbook may have (the cells of every one are read, a hidden one too). */
    protected const MAX_SHEETS = 500;

    /** Bytes of text a workbook may bring along: the shared strings, and each cell's own copy of its text. */
    private const MAX_TEXT_BYTES = 32 * 1024 * 1024;

    /** A number format of Excel is at most this long (characters); what is longer than that is no format of Excel's and is shown as General. */
    private const MAX_FORMAT_CODE = 255;

    /** What an entry of a table (a text, a style, a format, a relationship) costs besides what it says. */
    private const ENTRY = 64;

    /** What a sheet costs besides its name and relationship id (a table of three entries, as PHP keeps it). */
    private const SHEET = 512;

    /** Past the last row that is read, this many rows and cells are looked at for a value: a tail that is only formatted is not "cut off". */
    protected const TAIL_PROBE = 5_000;

    /** The clock and the memory are looked at after this many nodes of XML. */
    protected const CHECK_EVERY = 4096;

    /** What an Excel serial number of a workbook that counts from 1904 lacks. */
    private const DAYS_1900_TO_1904 = 1462;

    private ?ZipArchive $zip = null;

    private ?XMLReader $reading = null;

    private float $deadline = INF;

    /** Bytes of memory (as memory_get_usage() counts them) at which the reading gives up; 0: no limit. */
    private int $memoryAllowed = 0;

    /** Whether the part that is read has been read to the end of its root element. */
    private bool $ended = false;

    private int $nodes = 0;

    private int $walked = 0;

    private int $kept = 0;

    private int $bytes = 0;

    private int $maxCells = self::MAX_CELLS;

    /**
     * What decides that a workbook is too large, other than the settings: a change in one of these
     * is a reason to read a workbook again that was refused before.
     *
     * @return array<string, int>
     */
    public static function limits(): array
    {
        return [
            'inflated' => self::MAX_INFLATED_BYTES, 'walked' => static::MAX_WALKED, 'sheets' => static::MAX_SHEETS,
            'text' => self::MAX_TEXT_BYTES, 'format' => self::MAX_FORMAT_CODE, 'tail' => static::TAIL_PROBE,
        ];
    }

    /**
     * The sheets in the order of the workbook, one at a time.
     *
     * @param int $maxCells cells that hold a value, over all sheets; more than that is refused (TOO_LARGE)
     * @param float $seconds how long the reading may take; longer is refused (TOO_SLOW)
     * @return Generator<int, array{
     *     name: string, hidden: bool, truncated: bool,
     *     cells: array<int, array<int, array{0: string|float|bool, 1: ?string, 2: bool}>>
     * }> cells: row => column (1 = A) => [value, the number format when the value is a number, is it a formula].
     *    A date is a number (Excel's day count from 1900) with a date format. truncated: the sheet has more
     *    rows than $maxRows, or more columns than $maxColumns, that hold something.
     */
    public function sheets(string $file, int $maxRows, int $maxColumns, int $maxCells = self::MAX_CELLS, float $seconds = 15.0): Generator
    {
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The file is not a zip archive, so not an .xlsx workbook.');
        }
        $this->zip = $zip;
        [$this->deadline, $this->nodes, $this->walked, $this->kept, $this->bytes, $this->maxCells] = [microtime(true) + $seconds, 0, 0, 0, 0, $maxCells];
        // Half of what a script may use: the rest is for what is made of the cells afterwards.
        $this->memoryAllowed = intdiv($this->memoryLimit(), 2);

        // Faults in the XML are collected, not printed: a part that is not well-formed ends the reading.
        $errorsWere = libxml_use_internal_errors(true);
        try {
            $this->checkSize($zip);
            [$sheets, $date1904] = $this->workbook($zip);
            $parts = $this->parts($zip);
            foreach ($parts['named'] as $path) {
                if ($zip->locateName($path, ZipArchive::FL_NOCASE) === false) {
                    throw new RuntimeException("The part {$path} that the workbook names is not in the file.");
                }
            }
            $formats = $this->formats($zip, $parts['styles']);
            $texts = $this->texts($zip, $parts['strings']);

            foreach ($sheets as $sheet) {
                $part = $parts['sheets'][$sheet['relation']] ?? null;
                if ($part === null) {
                    // A sheet that is a chart (or a dialog, or a macro sheet) has a relationship but no part of
                    // cells: it is passed over. One that has no relationship at all is a package that is broken.
                    if (! isset($parts['kinds'][$sheet['relation']])) {
                        throw new RuntimeException('A sheet of the workbook has no relationship, so there is no telling where its cells are.');
                    }

                    continue;
                }

                yield ['name' => $sheet['name'], 'hidden' => $sheet['hidden']]
                    + $this->cells($zip, $part, $texts, $formats, $date1904, $maxRows, $maxColumns);
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errorsWere);
            $this->release();
        }
    }

    /**
     * Lets go of the file: the stream that is being read and the archive. A file that is still open
     * cannot be removed on Windows, so whoever removes it after a failure calls this first.
     */
    public function release(): void
    {
        if ($this->reading !== null) {
            @$this->reading->close();
            $this->reading = null;
        }
        if ($this->zip !== null) {
            @$this->zip->close();
            $this->zip = null;
        }
    }

    /** The archive says what it inflates to; a file that would inflate to more than is read is not opened at all. */
    private function checkSize(ZipArchive $zip): void
    {
        $inflated = 0;
        for ($at = 0; $at < $zip->numFiles; $at++) {
            $stat = $zip->statIndex($at);
            $inflated += is_array($stat) ? (int) $stat['size'] : 0;
            if ($inflated > self::MAX_INFLATED_BYTES) {
                throw new GraphException(GraphException::TOO_LARGE, 'The workbook inflates to more than ' . self::MAX_INFLATED_BYTES . ' bytes.');
            }
        }
    }

    /** @return array{0: array<int, array{name: string, hidden: bool, relation: string}>, 1: bool} the sheets, and whether dates count from 1904 */
    private function workbook(ZipArchive $zip): array
    {
        $sheets = [];
        $date1904 = false;
        $xml = $this->open($zip, 'xl/workbook.xml') ?? throw new RuntimeException('The archive has no xl/workbook.xml, so it is not an .xlsx workbook.');

        while ($this->next($xml)) {
            if ($xml->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            // Only the workbook's own elements: an extension (x15:workbookPr) has the same local name.
            if ($xml->depth === 1 && $xml->localName === 'workbookPr') {
                $date1904 = in_array($xml->getAttribute('date1904'), ['1', 'true'], true);
            } elseif ($xml->depth === 2 && $xml->localName === 'sheet') {
                if (count($sheets) >= static::MAX_SHEETS) {
                    throw new GraphException(GraphException::TOO_LARGE, 'The workbook has more than ' . static::MAX_SHEETS . ' sheets.');
                }
                $sheets[] = [
                    'name' => $name = (string) $xml->getAttribute('name'),
                    // "hidden" and "veryHidden" are both not shown in Excel.
                    'hidden' => ($xml->getAttribute('state') ?? 'visible') !== 'visible',
                    'relation' => $relation = self::relation($xml),
                ];
                $this->spend(strlen($name) + strlen($relation) + self::SHEET);
            }
        }
        $this->close($xml);

        return [$sheets, $date1904];
    }

    /**
     * Where the parts of the workbook are inside the archive.
     *
     * @return array{sheets: array<string, string>, kinds: array<string, string>, styles: string, strings: string, named: array<int, string>}
     *     sheets: relation id => path; kinds: relation id => what it is a relationship to (worksheet, chartsheet ...);
     *     named: every part a relationship names (a workbook without one of the optional parts has no
     *     relationship for it; one that names a part the file does not have is broken)
     */
    private function parts(ZipArchive $zip): array
    {
        $parts = ['sheets' => [], 'kinds' => [], 'styles' => 'xl/styles.xml', 'strings' => 'xl/sharedStrings.xml', 'named' => []];
        $xml = $this->open($zip, 'xl/_rels/workbook.xml.rels');
        if ($xml === null) {
            return $parts;
        }

        while ($this->next($xml)) {
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->localName !== 'Relationship' || $xml->getAttribute('TargetMode') === 'External') {
                continue;
            }
            $id = (string) $xml->getAttribute('Id');
            $path = self::resolve((string) $xml->getAttribute('Target'));
            $kind = basename((string) $xml->getAttribute('Type'));
            $parts['kinds'][$id] = $kind;
            $this->spend(strlen($id) + strlen($kind) + self::ENTRY);
            if (in_array($kind, ['worksheet', 'styles', 'sharedStrings'], true)) {
                $this->spend(strlen($path) + self::ENTRY);
                $parts['named'][] = $path;
            }
            match ($kind) {
                'worksheet' => $parts['sheets'][$id] = $path,
                'styles' => $parts['styles'] = $path,
                'sharedStrings' => $parts['strings'] = $path,
                default => null,
            };
        }
        $this->close($xml);

        return $parts;
    }

    /** @return array<int, string> the number format of each cell style, by the index a cell names its style with */
    private function formats(ZipArchive $zip, string $part): array
    {
        $own = [];
        $ids = [];
        $xml = $this->open($zip, $part);
        if ($xml === null) {
            return [];
        }

        $inCellStyles = false;
        $inFormats = false;
        while ($this->next($xml)) {
            if ($xml->localName === 'cellXfs') {
                // Not cellStyleXfs: those are the named styles, which cells do not point to.
                $inCellStyles = $xml->nodeType === XMLReader::ELEMENT && ! $xml->isEmptyElement;
            } elseif ($xml->localName === 'numFmts' && $xml->depth === 1) {
                $inFormats = $xml->nodeType === XMLReader::ELEMENT && ! $xml->isEmptyElement;
            } elseif ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'numFmt' && $inFormats) {
                // Only the formats of the workbook itself: a differential style (conditional formatting) may carry a
                // numFmt of its own with an id that a cell style uses for something else.
                $own[(int) $xml->getAttribute('numFmtId')] = $code = (string) $xml->getAttribute('formatCode');
                $this->spend(strlen($code) + self::ENTRY);
            } elseif ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'xf' && $inCellStyles) {
                $ids[] = (int) $xml->getAttribute('numFmtId');
                $this->spend(self::ENTRY);
            }
        }
        $this->close($xml);

        // A format of the workbook's own first, then the ones built into Excel. (Not "?:": the code of the
        // built-in format 1 is "0", which PHP takes for empty.)
        return array_map(function (int $id) use ($own) {
            $code = $own[$id] ?? NumberFormat::builtInFormatCode($id);

            // What Excel cannot have written (its formats are 255 characters at most) is not worked through the
            // library's patterns, which take longer with every character: the number is shown as it is.
            return $code === '' || strlen($code) > self::MAX_FORMAT_CODE ? NumberFormat::FORMAT_GENERAL : $code;
        }, $ids);
    }

    /** @return array<int, string> the texts cells refer to by number ("shared strings") */
    private function texts(ZipArchive $zip, string $part): array
    {
        $texts = [];
        $xml = $this->open($zip, $part);
        if ($xml === null) {
            return [];
        }

        while ($this->next($xml)) {
            if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'si') {
                $texts[] = $text = self::unescaped($this->text($xml));
                $this->spend(strlen($text) + self::ENTRY);
            }
        }
        $this->close($xml);

        return $texts;
    }

    /**
     * The cells of one sheet.
     *
     * @return array{truncated: bool, cells: array<int, array<int, array{0: string|float|bool, 1: ?string, 2: bool}>>}
     */
    private function cells(ZipArchive $zip, string $part, array $texts, array $formats, bool $date1904, int $maxRows, int $maxColumns): array
    {
        $cells = [];
        $truncated = false;
        $isDate = [];
        $row = 0;
        $column = 0;
        $beyond = false;
        $probed = 0;
        $xml = $this->open($zip, $part);
        if ($xml === null) {
            return ['truncated' => false, 'cells' => []];
        }

        while ($this->next($xml)) {
            if ($xml->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($xml->localName === 'row') {
                // Rows come in order, and both a row and a cell may leave out where they are.
                $row = max($row + 1, (int) $xml->getAttribute('r'));
                $column = 0;
                $beyond = $row > $maxRows;
                if ($beyond && ++$probed > static::TAIL_PROBE) {
                    break;
                }
            } elseif ($xml->localName === 'c') {
                if (++$this->walked > static::MAX_WALKED) {
                    throw new GraphException(GraphException::TOO_LARGE, 'The workbook has more than ' . static::MAX_WALKED . ' cells, counting those that only hold a format.');
                }
                if ($beyond) {
                    // Past the last row that is read: only to find out whether something is lost (an
                    // empty tail that only carries a format or a row height is not).
                    [$raw] = $this->content($xml);
                    $filled = $raw !== null && $raw !== '';
                    $truncated = $truncated || $filled;
                    if ($filled || ++$probed > static::TAIL_PROBE) {
                        break;
                    }

                    continue;
                }
                $column = preg_match('/^([A-Z]{1,3})\d/', (string) $xml->getAttribute('r'), $at) === 1 ? self::columnNumber($at[1]) : $column + 1;
                $type = $xml->getAttribute('t') ?? 'n';
                $format = $formats[(int) $xml->getAttribute('s')] ?? NumberFormat::FORMAT_GENERAL;
                [$raw, $formula] = $this->content($xml);
                if ($column > $maxColumns) {
                    $truncated = $truncated || ($raw !== null && $raw !== '');

                    continue;
                }
                if ($raw === null || $raw === '') {
                    continue;
                }

                $value = match ($type) {
                    's' => $texts[(int) $raw] ?? '',
                    'b' => $raw === '1',
                    // A date written out, as some programs save it: made the number Excel has for it.
                    'd' => self::serial($raw) ?? $raw,
                    'n' => is_numeric($raw) ? (float) $raw : $raw,
                    // "str" (the text a formula gives), "inlineStr", "e" (an error such as #N/A).
                    default => self::unescaped($raw),
                };
                if ($value === '') {
                    continue;
                }
                if (is_float($value)) {
                    $format = $type === 'd' ? 'yyyy-mm-dd hh:mm' : $format;
                    // A time of day (below 1) has no date to shift, and a length of time ([h]:mm) is not a date.
                    if ($date1904 && $type !== 'd' && $value >= 1 && ($isDate[$format] ??= self::isCalendar($format))) {
                        $value += self::DAYS_1900_TO_1904;
                    }
                }
                if (is_string($value)) {
                    $this->spend(strlen($value));
                }
                if (++$this->kept > $this->maxCells) {
                    throw new GraphException(GraphException::TOO_LARGE, "The workbook has more than {$this->maxCells} cells that hold a value.");
                }
                $cells[$row][$column] = [$value, is_float($value) ? $format : null, $formula];
            }
        }
        $this->close($xml);

        return ['truncated' => $truncated, 'cells' => $cells];
    }

    /** Counts what is kept of the workbook (text, and an entry of every table) against MAX_TEXT_BYTES. */
    private function spend(int $bytes): void
    {
        if (($this->bytes += $bytes) > self::MAX_TEXT_BYTES) {
            throw new GraphException(GraphException::TOO_LARGE, 'The workbook holds more than ' . self::MAX_TEXT_BYTES . ' bytes of text.');
        }
    }

    /**
     * What is inside a cell (<c>): the value as it is written (<v>, or the text of <is>), and
     * whether the cell has a formula (<f>).
     *
     * @return array{0: ?string, 1: bool}
     */
    private function content(XMLReader $xml): array
    {
        $raw = null;
        $formula = false;
        if ($xml->isEmptyElement) {
            return [$raw, $formula];
        }

        $depth = $xml->depth;
        while ($this->next($xml) && $xml->depth > $depth) {
            if ($xml->nodeType !== XMLReader::ELEMENT || $xml->depth !== $depth + 1) {
                continue;
            }
            match ($xml->localName) {
                'f' => $formula = true,
                'v' => $raw = (string) @$xml->readString(),
                'is' => $raw = $this->text($xml),
                default => null,
            };
        }

        return [$raw, $formula];
    }

    /** The text of a string item (<si> or <is>): its <t> pieces put together, without the reading aid of Japanese text (<rPh>). */
    private function text(XMLReader $xml): string
    {
        $text = '';
        if ($xml->isEmptyElement) {
            return $text;
        }

        $depth = $xml->depth;
        $aid = null;
        while ($this->next($xml) && $xml->depth > $depth) {
            if ($xml->nodeType === XMLReader::ELEMENT && $aid === null) {
                if ($xml->localName === 'rPh' && ! $xml->isEmptyElement) {
                    $aid = $xml->depth;
                } elseif ($xml->localName === 't') {
                    $text .= (string) @$xml->readString();
                }
            } elseif ($xml->nodeType === XMLReader::END_ELEMENT && $xml->depth === $aid) {
                $aid = null;
            }
        }

        return $text;
    }

    /** A part of the archive as XML to walk through, or null when the archive has no such part (whatever the case of its name). */
    private function open(ZipArchive $zip, string $part): ?XMLReader
    {
        $at = $zip->locateName($part, ZipArchive::FL_NOCASE);
        if ($at === false) {
            return null;
        }

        $xml = new XMLReader();
        libxml_clear_errors();
        // No network (LIBXML_NONET), and entities are left as they are: nothing outside the file is read.
        // ("@": a part that cannot be opened is a fault of the file, said below, not the warning that Laravel makes an exception of.)
        if (! @$xml->open('zip://' . $zip->filename . '#' . $zip->getNameIndex($at), null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException("The part {$part} of the workbook cannot be opened.");
        }
        $this->ended = false;

        return $this->reading = $xml;
    }

    private function close(XMLReader $xml): void
    {
        $xml->close();
        if ($this->reading === $xml) {
            $this->reading = null;
        }
    }

    /**
     * Moves on to the next node; false at the end. XML that is not well-formed is an error, not
     * an early end, and so is a document type declaration. A part that stops before its root element
     * is closed is a file that arrived damaged ("unavailable"): libxml takes a stream that breaks off
     * for the end of the document. The time and the memory are looked at now and then.
     */
    private function next(XMLReader $xml): bool
    {
        $more = @$xml->read();
        if (! $more) {
            $fault = libxml_get_last_error();
            if ($fault !== false && $fault->level === LIBXML_ERR_FATAL) {
                throw new RuntimeException('A part of the workbook is not well-formed XML: ' . trim($fault->message));
            }
            if (! $this->ended) {
                // The stream broke off, with nothing wrong in the XML up to there: the bytes arrived damaged. That is no verdict on
                // the workbook (a copy of it that was kept is not thrown away for it, and it is tried again).
                throw new GraphException(GraphException::UNAVAILABLE, 'A part of the workbook stops before its document is over: the file arrived damaged.');
            }

            return false;
        }
        if ($xml->depth === 0 && ($xml->nodeType === XMLReader::END_ELEMENT || ($xml->nodeType === XMLReader::ELEMENT && $xml->isEmptyElement))) {
            $this->ended = true;
        }
        if ($xml->nodeType === XMLReader::DOC_TYPE) {
            throw new RuntimeException('A part of the workbook has a document type declaration, which no workbook has.');
        }
        if ((++$this->nodes % static::CHECK_EVERY) === 0) {
            $this->checkTime();
            if ($this->memoryAllowed > 0 && memory_get_usage() > $this->memoryAllowed) {
                throw new GraphException(GraphException::TOO_LARGE, 'The workbook needs more memory than the application may spend on reading it.');
            }
        }

        return true;
    }

    /**
     * Fails (TOO_SLOW) when the time allowed for the reading is over. It is looked at while the XML is
     * walked, and by whoever works on what was read between one sheet and the next.
     */
    public function checkTime(): void
    {
        if (microtime(true) > $this->deadline) {
            throw new GraphException(GraphException::TOO_SLOW, 'Reading the workbook took longer than the time allowed for it.');
        }
    }

    /** The memory a script may use, in bytes; 0 when there is no limit. (A test lowers it here, not with ini_set(): what PHP has allocated is not what the test uses.) */
    protected function memoryLimit(): int
    {
        $limit = trim((string) ini_get('memory_limit'));
        $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return $limit === '-1' || $bytes <= 0 ? 0 : $bytes;
    }

    /** The id of the relationship an element points to (r:id), whatever prefix or edition of the format the file uses. */
    private static function relation(XMLReader $xml): string
    {
        $id = '';
        if ($xml->moveToFirstAttribute()) {
            do {
                if ($xml->localName === 'id' && str_ends_with((string) $xml->namespaceURI, '/relationships')) {
                    $id = $xml->value;
                }
            } while ($xml->moveToNextAttribute());
            $xml->moveToElement();
        }

        return $id;
    }

    /** A relationship's target as a path in the archive: relative to xl/, or from the root when it starts with "/"; "." and ".." resolved. */
    private static function resolve(string $target): string
    {
        $segments = str_starts_with(str_replace('\\', '/', $target), '/') ? [] : ['xl'];
        foreach (explode('/', str_replace('\\', '/', $target)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * Text as it was typed: Excel writes a character that XML cannot hold, and the carriage
     * return of a line break, as _xHHHH_ (and an underscore that could be taken for one as _x005F_).
     */
    private static function unescaped(string $text): string
    {
        if (! str_contains($text, '_x')) {
            return $text;
        }

        return (string) preg_replace_callback('/_x([0-9A-Fa-f]{4})_/', function (array $escape) {
            $code = (int) hexdec($escape[1]);

            // Of the control characters only tab and the line breaks are something to show.
            return $code < 32 && ! in_array($code, [9, 10, 13], true) ? '' : (string) mb_chr($code, 'UTF-8');
        }, $text);
    }

    /** Whether numbers of this format are dates or times of day (a length of time, [h]:mm, is not a date). */
    public static function isCalendar(string $format): bool
    {
        return Date::isDateTimeFormatCode($format) && preg_match('/\[(?:h+|m+|s+)\]/i', $format) !== 1;
    }

    /** Excel's number for a date that is written out (2026-10-06 or 2026-10-06T09:30:00); null for anything else. */
    private static function serial(string $written): ?float
    {
        if (preg_match('/^\d{4}-\d\d-\d\d(T\d\d:\d\d(:\d\d(\.\d+)?)?)?$/', $written) !== 1) {
            return null;
        }
        $moment = date_create_immutable($written, new DateTimeZone('UTC'));
        $serial = $moment === false ? false : Date::PHPToExcel($moment);

        return is_float($serial) ? $serial : null;
    }

    /** "A" => 1, "AB" => 28. */
    private static function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + ord($letter) - 64;
        }

        return $number;
    }
}
