<?php

namespace Modules\SharePointDocs\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use RuntimeException;
use Throwable;

/**
 * Turns the bytes of an Excel workbook (.xlsx) into plain tables: per sheet the column titles and
 * the rows, every cell as the text a reader sees in Excel. Nothing of the workbook is kept on
 * disk: the temporary file the reading needs is removed again.
 *
 * Formulas are not worked out here: a formula cell shows what Excel calculated when the workbook
 * was last saved.
 *
 * What goes wrong is told by what it is: bytes that are not a workbook at all are what something
 * in between sent instead of the file (a web filter's page): "unavailable", like any other answer
 * that is not Microsoft's. A workbook that is opened and found unreadable, or too big, is
 * "unsupported" or "too large".
 */
class WorkbookReader
{
    /** Rows of a sheet that are read; a longer sheet is cut off (and says so). */
    public const MAX_ROWS = 20000;

    /** Columns of a sheet that are read. */
    public const MAX_COLUMNS = 80;

    /** The column titles are looked for in this many rows from the top (a sheet may start with a heading). */
    private const TITLES_WITHIN = 10;

    /** A cell longer than this (bytes) is no web address, whatever it starts with: the blanks in it are not kept (see text()). */
    private const ADDRESS_MOST = 2048;

    /** How a workbook that is saved with a password begins (an old-style container around the real package). */
    private const ENCRYPTED = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /** A temporary copy of a workbook that is older than this (seconds) is the leftover of a reading that did not end. */
    private const LEFTOVER_AGE = 3600;

    /** What the temporary copies of a workbook are called (and what the folder they are kept in is). */
    private const COPY = 'mti-workbook';

    /** @var array<string, bool> number format => is it a date or time format */
    private array $dateFormats = [];

    /**
     * @param string|null $directory where the temporary copy of the workbook is made: a folder of this
     *     application's own, so that what is swept away is only what it left there (null: one in the
     *     temporary folder of the system)
     */
    public function __construct(private readonly XlsxReader $xlsx, private readonly ?string $directory = null)
    {
    }

    /**
     * @param bool $skipUnused leave out the rows of a register that is numbered ahead of use and
     *     that hold nothing but that number yet: rows with at most one cell that was typed in
     *     (cells filled by a formula do not count)
     * @param int $maxCells cells that hold a value (over all sheets) that a workbook may have
     * @param float $seconds how long the reading may take
     * @return array<int, array{
     *     name: string, hidden: bool, truncated: bool, columns: array<int, string>,
     *     rows: array<int, array<int, string>>, numbers: array<int, array<int, float>>, lines: array<int, int>,
     *     positions: array<int, int>, formulas: array<int, array<int, true>>
     * }> per sheet: titles, rows of text, the numbers behind cells that hold one (row => column => number, for
     *    sorting), each row's number in Excel, where each column is in Excel (1 = A) and the cells that hold a formula
     */
    public function read(string $bytes, bool $skipUnused = false, int $maxCells = XlsxReader::MAX_CELLS, float $seconds = 15.0): array
    {
        if (! str_starts_with($bytes, "PK\x03\x04")) {
            throw str_starts_with($bytes, self::ENCRYPTED)
                ? new GraphException(GraphException::UNSUPPORTED, 'The workbook is saved with a password (or is not an .xlsx workbook).')
                : new GraphException(GraphException::UNAVAILABLE, 'What came back is not a workbook (' . strlen($bytes) . ' bytes that do not start like a zip archive): something other than the file was sent.');
        }

        $directory = $this->copies();
        self::sweep($directory);

        // Not "@" for nothing: a failed write must come back as false here, not as the exception
        // that Laravel makes of a warning, and "unavailable" is what it is (the disk, not the workbook).
        $file = @tempnam($directory, 'wb');
        if ($file === false) {
            throw new GraphException(GraphException::UNAVAILABLE, 'No temporary file could be made for reading the workbook.');
        }
        // "finally" does not run after a fatal error (out of memory): the copy must not stay behind then
        // either, and a file that is still open cannot be removed on Windows.
        register_shutdown_function(function () use ($file) {
            $this->xlsx->release();
            if (is_file($file)) {
                @unlink($file);
            }
        });
        [$decimal, $thousands] = [StringHelper::getDecimalSeparator(), StringHelper::getThousandsSeparator()];

        try {
            if (@file_put_contents($file, $bytes) !== strlen($bytes)) {
                throw new GraphException(GraphException::UNAVAILABLE, 'The workbook could not be written to a temporary file.');
            }
            // Numbers as they are written here: 1.500.000,25 (the library's setting is put back below).
            StringHelper::setDecimalSeparator(',');
            StringHelper::setThousandsSeparator('.');

            $tables = [];
            foreach ($this->xlsx->sheets($file, self::MAX_ROWS + self::TITLES_WITHIN, self::MAX_COLUMNS, $maxCells, $seconds) as $sheet) {
                $tables[] = $this->table($sheet, $skipUnused);
            }

            return $tables;
        } catch (GraphException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            // The file is not what a workbook is (no workbook part, XML that is not well-formed ...).
            throw new GraphException(GraphException::UNSUPPORTED, 'The workbook could not be read: ' . mb_strimwidth($e->getMessage(), 0, 200, '…'));
        } catch (Throwable $e) {
            // A fault of this code, not of the workbook: it must not read as "not a workbook", and
            // what was kept is not thrown away for it. The log gets what is needed to find it.
            throw new GraphException(GraphException::UNAVAILABLE, sprintf(
                'Reading the workbook failed unexpectedly: %s: %s (%s:%d)',
                $e::class, mb_strimwidth($e->getMessage(), 0, 200, '…'), basename($e->getFile()), $e->getLine()
            ));
        } finally {
            $this->xlsx->release();
            StringHelper::setDecimalSeparator($decimal);
            StringHelper::setThousandsSeparator($thousands);
            @unlink($file);
        }
    }

    /** The folder of the temporary copies: the one that was given, or one in the temporary folder of the system; made when it is not there yet. */
    private function copies(): string
    {
        $directory = $this->directory ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::COPY;
        if (! is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }

        // Where it cannot be made, the temporary folder of the system (tempnam() would go there anyway).
        return is_dir($directory) ? $directory : sys_get_temp_dir();
    }

    /**
     * Removes the temporary copies that an earlier reading left behind (a fatal error leaves no
     * time to clean up, and on Windows a file that is still open cannot be removed then). Only
     * what is in this application's own folder, and what is known to be old: a file whose age
     * cannot be told is left alone.
     */
    private static function sweep(string $directory): void
    {
        $plain = fn (string $path) => rtrim(str_replace('\\', '/', $path), '/');
        if ($plain($directory) === $plain(sys_get_temp_dir())) {
            return; // the temporary folder of the system is shared with other programs: nothing in it is ours to remove
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . 'wb*') ?: [] as $old) {
            $modified = is_file($old) ? @filemtime($old) : false;
            if ($modified !== false && time() - $modified > self::LEFTOVER_AGE) {
                @unlink($old);
            }
        }
    }

    /** @param array{name: string, hidden: bool, truncated: bool, cells: array<int, array<int, array{0: string|float|bool, 1: ?string, 2: bool}>>} $sheet */
    private function table(array $sheet, bool $skipUnused): array
    {
        // What is in the sheet, as text: row => column => text. Empty cells are left out.
        $grid = [];
        $numbers = [];
        $typed = [];
        $formulas = [];
        foreach ($sheet['cells'] as $row => $cells) {
            // Making text of the cells is part of the reading, and has the same time.
            $this->xlsx->checkTime();
            foreach ($cells as $column => [$value, $format, $formula]) {
                [$text, $number] = $this->text($value, $format);
                if ($formula) {
                    $formulas[$row][$column] = true;
                }
                if ($text === '') {
                    continue;
                }
                $grid[$row][$column] = $text;
                if ($number !== null) {
                    $numbers[$row][$column] = $number;
                }
                if (! $formula) {
                    $typed[$row] = ($typed[$row] ?? 0) + 1;
                }
            }
        }
        ksort($grid);

        $table = [
            'name' => $sheet['name'],
            'hidden' => $sheet['hidden'],
            'truncated' => $sheet['truncated'],
            'columns' => [],
            'rows' => [],
            'numbers' => [],
            'lines' => [],
            'positions' => [],
            'formulas' => [],
        ];
        $titlesAt = $this->titlesAt($grid);
        if ($titlesAt === null) {
            return $table;
        }

        // The rows: everything below the titles.
        $below = [];
        foreach ($grid as $row => $cells) {
            if ($row > $titlesAt && ! ($skipUnused && ($typed[$row] ?? 0) <= 1)) {
                $below[$row] = $cells;
            }
        }

        // The columns: every one that has a title or anything in the rows.
        $used = array_fill_keys(array_keys($grid[$titlesAt]), true);
        foreach ($below as $cells) {
            $used += array_fill_keys(array_keys($cells), true);
        }
        ksort($used);
        $positions = array_keys($used);
        // Where each column is in Excel (1 = A): what a cell is written to is told by this and the row's number.
        $table['positions'] = $positions;
        foreach ($positions as $column) {
            $table['columns'][] = $grid[$titlesAt][$column] ?? 'Kolom ' . Coordinate::stringFromColumnIndex($column);
        }

        foreach ($below as $row => $cells) {
            $at = count($table['rows']);
            $table['lines'][] = $row;
            $table['rows'][] = array_map(fn (int $column) => $cells[$column] ?? '', $positions);
            foreach ($positions as $position => $column) {
                if (isset($numbers[$row][$column])) {
                    $table['numbers'][$at][$position] = $numbers[$row][$column];
                }
                // The cells that hold a formula (row => column in the table): they are not written over.
                if (isset($formulas[$row][$column])) {
                    $table['formulas'][$at][$position] = true;
                }
            }
        }

        return $table;
    }

    /**
     * The row with the column titles: the first of the top rows that is filled like a row of
     * titles (three cells or more, or as many as the fullest of those rows has). A heading above
     * the table, in one cell, is passed over.
     *
     * @param array<int, array<int, string>> $grid
     */
    private function titlesAt(array $grid): ?int
    {
        $top = array_filter($grid, fn (int $row) => $row <= self::TITLES_WITHIN, ARRAY_FILTER_USE_KEY);
        if ($top === []) {
            return $grid === [] ? null : array_key_first($grid);
        }

        $enough = min(3, max(array_map('count', $top)));
        foreach ($top as $row => $cells) {
            if (count($cells) >= $enough) {
                return $row;
            }
        }

        return array_key_first($top);
    }

    /**
     * What a reader sees in the cell, and the number behind it when the cell holds a number or a
     * date (text that looks like a number, say a serial number "000123", is text).
     *
     * @return array{0: string, 1: ?float}
     */
    private function text(string|float|bool $value, ?string $format): array
    {
        if (is_bool($value)) {
            return [$value ? 'TRUE' : 'FALSE', null];
        }
        if (is_float($value)) {
            $format = (string) $format;
            // (A length of time, [h]:mm: 36:00, is a number written in hours and no date: it is not 1900-01-02.)
            $isDate = $this->dateFormats[$format] ??= XlsxReader::isCalendar($format);

            return [$isDate ? self::date($value) : self::number($value, $format), $value];
        }

        // One line per cell in the table: line breaks inside a cell become spaces, and so does any run of blanks.
        $text = trim((string) preg_replace('/\s+/u', ' ', $value));
        if (preg_match('#^https://#i', $text) !== 1 || strlen($value) > self::ADDRESS_MOST) {
            return [$text, null];
        }

        // Except in a web address: the name of a file in a link is compared with the names of the files letter for letter
        // ("Andi  Wijaya.pdf" is not "Andi Wijaya.pdf" to SharePoint), so the blanks inside it stay as they were typed.
        // (Not in a cell that is longer than any address: it is no link, and the patterns below are slow on a long run of blanks.)
        return [(string) preg_replace('/^\s+|\s+$/u', '', (string) preg_replace('/\s*[\r\n]+\s*/u', ' ', $value)), null];
    }

    /** An Excel date as it is written in this application: 06-10-2026, with the time when the cell has one. */
    private static function date(float $serial): string
    {
        try {
            $moment = Date::excelToDateTimeObject($serial);
        } catch (Throwable) {
            return self::number($serial, NumberFormat::FORMAT_GENERAL); // not a date Excel could show either
        }
        $time = abs($serial - floor($serial)) > 1 / 86400 / 2;

        return match (true) {
            $serial >= 0 && $serial < 1 => $moment->format('H:i'),
            $time => $moment->format('d-m-Y H:i'),
            default => $moment->format('d-m-Y'),
        };
    }

    private static function number(float $value, string $format): string
    {
        if (in_array($format, ['', NumberFormat::FORMAT_GENERAL, NumberFormat::FORMAT_TEXT], true)) {
            // A whole number as its digits (a badge number, an IMEI), anything else as short as it is.
            if (floor($value) == $value && abs($value) < 1e15) {
                return number_format($value, 0, '', '');
            }
            // Very small and very large numbers the way Excel writes them: 1,5E-12, not "0" and not digits that are not in the file.
            if (is_finite($value) && (($value != 0 && abs($value) < 1e-6) || abs($value) >= 1e15)) {
                return self::scientific($value);
            }

            return rtrim(rtrim(number_format($value, 10, ',', ''), '0'), ',');
        }

        try {
            $text = trim((string) NumberFormat::toFormattedString($value, $format));
        } catch (Throwable) {
            return (string) $value;
        }

        // The library writes the separators set in read() only where it groups thousands of a plain
        // number (#,##0.00). Everywhere else (0.00, 0.0%, #,##0.00%, 0.00E+00, "Rp "0.00) the decimal
        // separator of the number is a point.
        $digits = (string) preg_replace('/"[^"]*"|\\\\.|_.|\*./', '', $format); // without the texts and the spacing
        $grouped = preg_match('/[#0?],[#0?]/', $digits) === 1 && ! str_contains($digits, '%');
        if (! $grouped && preg_match('/\.[#0?]/', $digits) === 1) {
            $text = (string) preg_replace('/(?<=\d)\.(?=\d)/', ',', $text, 1);
        }
        // The library writes the exponent of 0.00E+00 as 1,20E-4; Excel gives it as many digits as there are zeros after the sign: E-04.
        if (preg_match('/E[+-](0+)/i', $digits, $zeros) === 1) {
            $text = (string) preg_replace_callback(
                '/E([+-])(\d+)(?=\D*$)/i',
                fn (array $exponent) => 'E' . $exponent[1] . str_pad($exponent[2], strlen($zeros[1]), '0', STR_PAD_LEFT),
                $text,
                1
            );
        }

        return $text;
    }

    /** A number in scientific notation, the way Excel writes it when it does not fit: 1,5E-12, 1,23456789012346E+19 (15 digits at most, a decimal comma). */
    private static function scientific(float $value): string
    {
        [$mantissa, $exponent] = explode('E', sprintf('%.14E', $value));

        return str_replace('.', ',', rtrim(rtrim($mantissa, '0'), '.'))
            . 'E' . $exponent[0] . str_pad(ltrim(substr($exponent, 1), '0'), 2, '0', STR_PAD_LEFT);
    }
}
