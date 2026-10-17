<?php

namespace Modules\SharePointDocs\Services;

use Closure;
use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Reads, and writes, ONE cell of an Excel workbook (.xlsx) inside its bytes.
 *
 * Why not the Excel API of Microsoft Graph: it is not offered to an application that signs in as itself
 * (only to a signed-in user), and this application changes the workbook on its own behalf, after somebody
 * of its own has approved the change. So the file is changed, and put back (see SharePointWorkbook::replace).
 *
 * Why not a library that builds the workbook up and writes it again: what it does not know (charts, pivot
 * tables, slicers, macros, the look of a conditional format) it loses, and this is somebody's register. Here
 * the text of one worksheet part is changed at the one place the cell is, and every other part of the archive
 * is carried over as it is. A workbook of which the sheet is written in a shape that is not known (another
 * prefix for the elements, rows without their number) is refused, never guessed at.
 *
 * A cell that holds a formula is never written over (read() says so; whoever writes asks first).
 */
final class XlsxPatcher
{
    /** The relationship attribute of a sheet of the workbook ("r:id"). */
    private const RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** A worksheet part that inflates to more than this is not changed here (bytes). */
    private const MAX_PART_BYTES = 192 * 1024 * 1024;

    /** How many times a change is made and saved before the save counts as failed (see write()). */
    private const SAVE_ATTEMPTS = 3;

    /**
     * @param  (Closure(ZipArchive): bool)|null  $save  saves and closes the archive of a change: ZipArchive::close(), unless
     *     a test gives one that fails.
     */
    public function __construct(private readonly ?Closure $save = null)
    {
    }

    /**
     * The cell as the workbook has it.
     *
     * @return array{
     *     exists: bool, formula: bool, type: string, raw: ?string, style: ?string, supported: bool, date1904: bool
     * } raw: the value as written (a shared string is looked up; for a boolean "1" or "0"); null for an empty cell.
     *     supported: false for a cell of a kind that is not written here (it carries cell metadata, "cm" / "vm").
     */
    public function read(string $bytes, string $sheet, string $address): array
    {
        return $this->inArchive($bytes, false, function (ZipArchive $zip) use ($sheet, $address): array {
            [$part, $date1904] = $this->locate($zip, $sheet);
            $xml = $this->part($zip, $part);
            $cell = $this->cell($xml, $address);
            if ($cell === null) {
                return ['exists' => false, 'formula' => false, 'type' => 'n', 'raw' => null, 'style' => null, 'supported' => true, 'date1904' => $date1904];
            }

            $attributes = $cell['attributes'];
            $type = $attributes['t'] ?? 'n';
            $raw = null;
            $formula = false;
            if ($cell['inner'] !== '') {
                $formula = preg_match('/<f[\s>\/]/', $cell['inner']) === 1;
                if (preg_match('#<v>(.*?)</v>#s', $cell['inner'], $value) === 1) {
                    $raw = self::unescaped($value[1]);
                    if ($type === 's') {
                        $raw = $this->sharedText($zip, (int) $raw);
                    }
                } elseif (preg_match('#<is>(.*?)</is>#s', $cell['inner'], $inline) === 1) {
                    $raw = self::stringItem($inline[1]);
                }
            }

            return [
                'exists' => true,
                'formula' => $formula,
                'type' => $type,
                'raw' => $raw === '' ? null : $raw,
                'style' => $attributes['s'] ?? null,
                'supported' => ! isset($attributes['cm']) && ! isset($attributes['vm']),
                'date1904' => $date1904,
            ];
        });
    }

    /**
     * The workbook with $address of $sheet holding $value: text stays text (an "inline string": "000123" is not
     * turned into a number), a number is a number, null or "" empties the cell. The look of the cell (its style) stays.
     * Excel is asked to work out every formula again when it opens the file, because what depends on the cell
     * has the value of before in the file.
     */
    public function write(string $bytes, string $sheet, string $address, string|float|null $value): string
    {
        if (preg_match('/^([A-Z]{1,3})([1-9]\d{0,6})$/', $address, $at) !== 1) {
            throw new RuntimeException("\"{$address}\" is not the address of a cell.");
        }
        if (is_string($value) && ! self::isXmlText($value)) {
            throw new RuntimeException('The text has characters that a workbook cannot hold.');
        }

        // Saving the archive replaces its temporary copy with a new file. On Windows that fails now and then while another
        // process (a virus scanner) still holds the fresh copy, and the copy keeps the workbook as it was (measured: about
        // one save in two hundred). The change is then made again on a new copy; a workbook without it never comes back.
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->inArchive($bytes, true, fn (ZipArchive $zip, string $file): string => $this->change($zip, $file, $sheet, $address, $at, $value));
            } catch (WorkbookNotSaved $e) {
                if ($attempt >= self::SAVE_ATTEMPTS) {
                    throw $e;
                }
                usleep(100000 * $attempt);
            }
        }
    }

    /**
     * Changes the cell in the open archive, saves it, and gives the saved workbook back.
     *
     * @param  array<int, string>  $at  the parts of the address: [1] the column letters, [2] the row number
     */
    private function change(ZipArchive $zip, string $file, string $sheet, string $address, array $at, string|float|null $value): string
    {
        [$part] = $this->locate($zip, $sheet);
        $xml = $this->part($zip, $part);

        $cell = $this->cell($xml, $address);
        if ($cell !== null) {
            if (isset($cell['attributes']['cm']) || isset($cell['attributes']['vm'])) {
                throw new RuntimeException("The cell {$address} carries metadata of Excel (an array or a linked type) and is not written here.");
            }
            $new = self::cellXml($address, $cell['attributes']['s'] ?? null, $value);
            $xml = substr_replace($xml, $new, $cell['start'], $cell['length']);
        } else {
            $new = self::cellXml($address, null, $value);
            $xml = $this->insert($xml, (int) $at[2], self::columnNumber($at[1]), $new);
        }

        $zip->addFromString($part, $xml);
        $zip->setCompressionName($part, ZipArchive::CM_DEFLATE);
        $this->recalculate($zip);

        // (Silenced: what went wrong is asked of the archive, and said by the exception.)
        if (! ($this->save ?? static fn (ZipArchive $archive): bool => @$archive->close())($zip)) {
            try {
                $reason = $zip->getStatusString();
            } catch (\ValueError) {
                $reason = 'no reason given';
            }
            throw new WorkbookNotSaved("The changed workbook could not be saved: {$reason}.");
        }

        $written = @file_get_contents($file);
        if (! is_string($written) || ! str_starts_with($written, "PK\x03\x04")) {
            throw new RuntimeException('The changed workbook could not be read back from the temporary file.');
        }
        // What goes back to SharePoint has the cell as it was just written: a save that did not happen leaves the old one.
        if (! str_contains($this->inArchive($written, false, fn (ZipArchive $saved): string => $this->part($saved, $part)), $new)) {
            throw new WorkbookNotSaved('The saved workbook does not have the change.');
        }

        return $written;
    }

    /**
     * Runs $work with the archive open, on a temporary copy of the bytes that is removed afterwards.
     *
     * @template T
     * @param callable(ZipArchive, string): T $work
     * @return T
     */
    private function inArchive(string $bytes, bool $writable, callable $work): mixed
    {
        if (! str_starts_with($bytes, "PK\x03\x04")) {
            throw new RuntimeException('The bytes are not an .xlsx workbook (they do not start like a zip archive).');
        }
        $file = @tempnam(sys_get_temp_dir(), 'xlp');
        if ($file === false) {
            throw new RuntimeException('No temporary file could be made for the workbook.');
        }

        $zip = new ZipArchive();
        try {
            if (@file_put_contents($file, $bytes) !== strlen($bytes)) {
                throw new RuntimeException('The workbook could not be written to a temporary file.');
            }
            if ($zip->open($file, $writable ? 0 : ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('The file is not a zip archive, so not an .xlsx workbook.');
            }

            return $work($zip, $file);
        } finally {
            // (A file that is still open cannot be removed on Windows. write() closes the archive itself, to
            // read the result: closing it a second time is an error that does not matter.)
            try {
                $zip->close();
            } catch (\ValueError) {
            }
            @unlink($file);
        }
    }

    /**
     * Where the cells of a sheet are in the archive, and whether the workbook counts its dates from 1904.
     *
     * @return array{0: string, 1: bool}
     */
    private function locate(ZipArchive $zip, string $sheet): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        if (! is_string($workbook) || str_contains($workbook, '<!DOCTYPE')) {
            throw new RuntimeException('The archive has no xl/workbook.xml that can be read, so it is not an .xlsx workbook.');
        }

        $relation = null;
        $date1904 = false;
        $exact = null;
        $loose = null;
        $xml = XMLReader::XML($workbook, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $xml instanceof XMLReader) {
            throw new RuntimeException('xl/workbook.xml cannot be read.');
        }
        while (@$xml->read()) {
            if ($xml->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($xml->depth === 1 && $xml->localName === 'workbookPr') {
                $date1904 = in_array($xml->getAttribute('date1904'), ['1', 'true'], true);
            } elseif ($xml->depth === 2 && $xml->localName === 'sheet') {
                $name = (string) $xml->getAttribute('name');
                $id = (string) $xml->getAttributeNs('id', self::RELATIONSHIPS);
                if ($name === $sheet) {
                    $exact ??= $id;
                } elseif (mb_strtolower(trim($name)) === mb_strtolower(trim($sheet))) {
                    $loose ??= $id;
                }
            }
        }
        $xml->close();
        $relation = $exact ?? $loose;
        if ($relation === null || $relation === '') {
            throw new SheetNotFound("The workbook has no sheet \"{$sheet}\".");
        }

        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (! is_string($rels) || str_contains($rels, '<!DOCTYPE')) {
            throw new RuntimeException('The archive has no xl/_rels/workbook.xml.rels that can be read.');
        }
        $reading = XMLReader::XML($rels, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $reading instanceof XMLReader) {
            throw new RuntimeException('xl/_rels/workbook.xml.rels cannot be read.');
        }
        $target = null;
        while (@$reading->read()) {
            if ($reading->nodeType === XMLReader::ELEMENT && $reading->localName === 'Relationship'
                && $reading->getAttribute('Id') === $relation && $reading->getAttribute('TargetMode') !== 'External'
                && basename((string) $reading->getAttribute('Type')) === 'worksheet') {
                $target = (string) $reading->getAttribute('Target');
                break;
            }
        }
        $reading->close();
        if ($target === null || $target === '') {
            throw new SheetNotFound("The sheet \"{$sheet}\" has no worksheet part (it is a chart, or a macro sheet).");
        }

        return [self::resolve($target), $date1904];
    }

    /** The text of a worksheet part, as long as it is made like the ones that are known here. */
    private function part(ZipArchive $zip, string $part): string
    {
        $stat = $zip->statName($part);
        if ($stat === false || $stat['size'] > self::MAX_PART_BYTES) {
            throw new RuntimeException("The part {$part} is missing or too large to be changed here.");
        }
        $xml = $zip->getFromName($part);
        if (! is_string($xml) || str_contains($xml, '<!DOCTYPE')) {
            throw new RuntimeException("The part {$part} cannot be read.");
        }
        if (preg_match('/<sheetData[\s>\/]/', $xml) !== 1) {
            throw new RuntimeException("The part {$part} is written in a shape that is not known here (no plain <sheetData>).");
        }

        return $xml;
    }

    /**
     * Where a cell is in the text of a sheet.
     *
     * @return array{start: int, length: int, attributes: array<string, string>, inner: string}|null
     */
    private function cell(string $xml, string $address): ?array
    {
        if (preg_match('/^[A-Z]{1,3}([1-9]\d{0,6})$/', $address, $at) !== 1) {
            throw new RuntimeException("\"{$address}\" is not the address of a cell.");
        }
        $row = $this->row($xml, (int) $at[1]);
        if ($row === null || $row['inner'] === null) {
            return null;
        }

        $start = self::tagWith($row['inner'], 'c', $address);
        if ($start === null) {
            return null;
        }
        $open = self::openTag($row['inner'], $start);
        $length = strlen($open);
        $inner = '';
        if (! str_ends_with($open, '/>')) {
            $close = strpos($row['inner'], '</c>', $start + $length);
            if ($close === false) {
                throw new RuntimeException("The cell {$address} is not closed.");
            }
            $inner = substr($row['inner'], $start + $length, $close - $start - $length);
            $length = $close + 4 - $start;
        }

        return ['start' => $row['innerStart'] + $start, 'length' => $length, 'attributes' => self::attributes($open), 'inner' => $inner];
    }

    /**
     * Where a row is in the text of a sheet: its opening tag, and what is inside it (null for a row that is empty).
     *
     * @return array{start: int, tagLength: int, innerStart: int, inner: ?string, end: int}|null
     */
    private function row(string $xml, int $number): ?array
    {
        $start = self::tagWith($xml, 'row', (string) $number);
        if ($start === null) {
            return null;
        }
        $tag = self::openTag($xml, $start);
        $tagLength = strlen($tag);
        if (str_ends_with($tag, '/>')) {
            return ['start' => $start, 'tagLength' => $tagLength, 'innerStart' => $start + $tagLength, 'inner' => null, 'end' => $start + $tagLength];
        }
        $close = strpos($xml, '</row>', $start + $tagLength);
        if ($close === false) {
            throw new RuntimeException("The row {$number} is not closed.");
        }

        return [
            'start' => $start,
            'tagLength' => $tagLength,
            'innerStart' => $start + $tagLength,
            'inner' => substr($xml, $start + $tagLength, $close - $start - $tagLength),
            'end' => $close + 6,
        ];
    }

    /**
     * Where the opening tag of an element starts that has the attribute r="$value" ("row" 5, "c" "D5"), at or after $from.
     *
     * Found by looking for the attribute as text, never by a pattern run over the whole sheet: a sheet has tens of thousands of
     * rows, and a pattern that gives up on a text that long (PHP's limits on matching) says "not there" as it says it
     * for a row that really is not, which would write a second row of the same number.
     */
    private static function tagWith(string $xml, string $tag, string $value, int $from = 0): ?int
    {
        $needle = ' r="' . $value . '"';
        $length = strlen($xml);
        for ($at = strpos($xml, $needle, $from); $at !== false; $at = strpos($xml, $needle, $at + 1)) {
            // The attribute has to be inside the opening tag of an element of this name.
            $start = strrpos($xml, '<', $at - $length);
            if ($start === false) {
                continue;
            }
            $end = strpos($xml, '>', $start);
            $name = '<' . $tag;
            if ($end !== false && $end > $at && substr_compare($xml, $name, $start, strlen($name)) === 0
                && in_array($xml[$start + strlen($name)] ?? '', [' ', "\t", "\r", "\n"], true)) {
                return $start;
            }
        }

        return null;
    }

    /** The opening tag that starts at $start: up to and with its ">". */
    private static function openTag(string $xml, int $start): string
    {
        $end = strpos($xml, '>', $start);
        if ($end === false) {
            throw new RuntimeException('A tag of the sheet is not closed.');
        }

        return substr($xml, $start, $end - $start + 1);
    }

    /**
     * The number of the row (or the column, for a cell) that an element names with its r="...": the opening tags of
     * $tag from $from on, one after the other, as [offset, number]. Walked one at a time, like tagWith(), not by a pattern.
     *
     * @return \Generator<int, array{0: int, 1: string}>
     */
    private static function tagsOf(string $xml, string $tag, int $from = 0, ?int $until = null): \Generator
    {
        $name = '<' . $tag;
        for ($at = strpos($xml, $name, $from); $at !== false && ($until === null || $at < $until); $at = strpos($xml, $name, $at + 1)) {
            // "<row" and not "<rowBreaks", "<c" and not "<cols": the name is followed by a blank.
            if (! in_array($xml[$at + strlen($name)] ?? '', [' ', "\t", "\r", "\n"], true)) {
                continue;
            }
            $attributes = self::attributes(self::openTag($xml, $at));
            if (isset($attributes['r'])) {
                yield [$at, $attributes['r']];
            }
        }
    }

    /** Puts a new cell where it belongs: in its row, in the order of the columns; a row that is not there is made. */
    private function insert(string $xml, int $row, int $column, string $cell): string
    {
        $found = $this->row($xml, $row);
        if ($found !== null) {
            $tag = substr($xml, $found['start'], $found['tagLength']);
            // The hint of the columns the row spans may not be true any more.
            $opened = (string) preg_replace('/\sspans="[^"]*"/', '', $tag);

            if ($found['inner'] === null) {
                // <row r="5" .../> : opened and closed with the cell in it.
                $opened = substr($opened, 0, -2) . '>';

                return substr_replace($xml, $opened . $cell . '</row>', $found['start'], $found['tagLength']);
            }

            $at = strlen($found['inner']);
            foreach (self::tagsOf($found['inner'], 'c') as [$offset, $name]) {
                if (preg_match('/^([A-Z]{1,3})\d+$/', $name, $letters) === 1 && self::columnNumber($letters[1]) > $column) {
                    $at = $offset;
                    break;
                }
            }
            $inner = substr($found['inner'], 0, $at) . $cell . substr($found['inner'], $at);

            return substr_replace($xml, $opened . $inner . '</row>', $found['start'], $found['end'] - $found['start']);
        }

        $new = '<row r="' . $row . '">' . $cell . '</row>';
        $end = strrpos($xml, '</sheetData>');
        // Before the first row that comes after this one, else at the end of the data.
        foreach (self::tagsOf($xml, 'row', 0, $end === false ? null : $end) as [$offset, $number]) {
            if (ctype_digit($number) && (int) $number > $row) {
                return substr_replace($xml, $new, $offset, 0);
            }
        }
        if (preg_match('/<sheetData\s*\/>/', $xml, $empty, PREG_OFFSET_CAPTURE) === 1) {
            return substr_replace($xml, '<sheetData>' . $new . '</sheetData>', $empty[0][1], strlen($empty[0][0]));
        }
        if ($end === false) {
            throw new RuntimeException('The sheet has no end of its data.');
        }

        return substr_replace($xml, $new, $end, 0);
    }

    /** Asks Excel to work out the formulas again when the file is opened (only where the workbook already has its settings of calculation). */
    private function recalculate(ZipArchive $zip): void
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        if (! is_string($workbook) || preg_match('/<calcPr\b[^>]*>/', $workbook, $tag) !== 1 || str_contains($tag[0], 'fullCalcOnLoad')) {
            return;
        }
        $zip->addFromString('xl/workbook.xml', substr_replace(
            $workbook,
            str_replace('<calcPr', '<calcPr fullCalcOnLoad="1"', $tag[0]),
            (int) strpos($workbook, $tag[0]),
            strlen($tag[0])
        ));
        $zip->setCompressionName('xl/workbook.xml', ZipArchive::CM_DEFLATE);
    }

    /** The text of the shared string with this number. */
    private function sharedText(ZipArchive $zip, int $index): string
    {
        $part = 'xl/sharedStrings.xml';
        $texts = $zip->getFromName($part);
        if (! is_string($texts) || str_contains($texts, '<!DOCTYPE')) {
            return '';
        }

        $xml = XMLReader::XML($texts, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if (! $xml instanceof XMLReader) {
            return '';
        }
        $at = -1;
        while (@$xml->read()) {
            if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'si' && ++$at === $index) {
                $item = (string) $xml->readOuterXml();
                $xml->close();

                return self::stringItem($item);
            }
        }
        $xml->close();

        return '';
    }

    /** The text of a string item (<si> or <is>): its <t> pieces put together, without the reading aid of Japanese text (<rPh>). */
    private static function stringItem(string $xml): string
    {
        $xml = (string) preg_replace('#<rPh\b.*?</rPh>#s', '', $xml);
        preg_match_all('#<t(?:\s[^>]*)?>(.*?)</t>#s', $xml, $pieces);

        return self::unescaped(implode('', $pieces[1]));
    }

    /** @return array<string, string> the attributes of an opening tag */
    private static function attributes(string $tag): array
    {
        preg_match_all('/\s([A-Za-z_:][\w:.\-]*)="([^"]*)"/', $tag, $found, PREG_SET_ORDER);
        $attributes = [];
        foreach ($found as [, $name, $value]) {
            $attributes[$name] = $value;
        }

        return $attributes;
    }

    private static function cellXml(string $address, ?string $style, string|float|null $value): string
    {
        $style = $style !== null && preg_match('/^\d{1,6}$/', $style) === 1 ? ' s="' . $style . '"' : '';

        if ($value === null || $value === '') {
            return "<c r=\"{$address}\"{$style}/>";
        }
        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new RuntimeException('The number is not a number a cell can hold.');
            }
            // Digits only (no exponent): what Excel reads back to the same number, 15 digits are what it keeps.
            $text = rtrim(rtrim(sprintf('%.15F', $value), '0'), '.');

            return "<c r=\"{$address}\"{$style}><v>" . ($text === '' || $text === '-0' ? '0' : $text) . '</v></c>';
        }

        return "<c r=\"{$address}\"{$style} t=\"inlineStr\"><is><t xml:space=\"preserve\">"
            . htmlspecialchars($value, ENT_XML1 | ENT_NOQUOTES, 'UTF-8') . '</t></is></c>';
    }

    /** What the text of a cell may hold: the characters XML 1.0 has, and no more. */
    private static function isXmlText(string $text): bool
    {
        return mb_check_encoding($text, 'UTF-8')
            && preg_match('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', $text) !== 1;
    }

    /** XML's own escapes, and the ones of Excel ("_x000D_"). */
    private static function unescaped(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return (string) preg_replace_callback('/_x([0-9A-Fa-f]{4})_/', function (array $escape) {
            $character = mb_chr((int) hexdec($escape[1]), 'UTF-8');

            return $character === false ? $escape[0] : $character;
        }, $text);
    }

    /** "../worksheets/sheet1.xml" or "/xl/worksheets/sheet1.xml" as the path inside the archive. */
    private static function resolve(string $target): string
    {
        $parts = [];
        foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }

        return implode('/', $parts);
    }

    public static function columnNumber(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + (ord($letter) - 64);
        }

        return $number;
    }

    public static function columnLetters(int $number): string
    {
        $letters = '';
        for (; $number > 0; $number = intdiv($number - 1, 26)) {
            $letters = chr(65 + ($number - 1) % 26) . $letters;
        }

        return $letters;
    }
}
