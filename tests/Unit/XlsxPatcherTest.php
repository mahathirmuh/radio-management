<?php

namespace Tests\Unit;

use Modules\SharePointDocs\Services\WorkbookReader;
use Modules\SharePointDocs\Services\XlsxPatcher;
use Modules\SharePointDocs\Services\XlsxReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Concerns\MakesWorkbooks;
use ZipArchive;

/**
 * Writing ONE cell of a workbook inside its bytes: the cell is what changes, and everything else of the
 * file (the other cells, their styles, every other part of the archive) is carried over as it was.
 */
class XlsxPatcherTest extends TestCase
{
    use MakesWorkbooks;

    private function book(): string
    {
        return self::xlsx([
            'SRF' => [
                ['SRF No', 'Date', 'Requested By', 'Qty', 'Serial Number', 'Check'],
                ['ICT/SRF/1', ['value' => 45000, 'format' => 'd-mmm-yy'], 'Budi Santoso', 2, '000123', ['formula' => '=D2*2']],
                ['ICT/SRF/2', ['value' => 45001.5, 'format' => 'dd/mm/yyyy hh:mm'], 'Andi Wijaya', 10, 'SN-9', null],
                [null, null, null, null, null, null],
                ['ICT/SRF/4', null, 'Citra Dewi', null, null, null],
            ],
            'Lain' => [
                ['A', 'B'],
                ['x', 'y'],
            ],
        ]);
    }

    /** What WorkbookReader makes of the bytes: sheet name => rows (as text). */
    private function tables(string $bytes): array
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xp-' . bin2hex(random_bytes(3));
        try {
            $tables = [];
            foreach ((new WorkbookReader(new XlsxReader(), $directory))->read($bytes) as $table) {
                $tables[$table['name']] = $table;
            }

            return $tables;
        } finally {
            foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }

    public function test_it_reads_a_cell_as_the_workbook_has_it(): void
    {
        $patcher = new XlsxPatcher();
        $bytes = $this->book();

        $this->assertSame('Budi Santoso', $patcher->read($bytes, 'SRF', 'C2')['raw']);
        $this->assertSame('000123', $patcher->read($bytes, 'SRF', 'E2')['raw']);
        $this->assertSame('45000', $patcher->read($bytes, 'SRF', 'B2')['raw']);
        $this->assertSame('2', $patcher->read($bytes, 'SRF', 'D2')['raw']);
        $this->assertTrue($patcher->read($bytes, 'SRF', 'F2')['formula']);
        $this->assertFalse($patcher->read($bytes, 'SRF', 'C2')['formula']);

        // A cell that holds nothing, in a row that is there and in one that is not.
        $this->assertSame(['exists' => false, 'raw' => null], array_intersect_key($patcher->read($bytes, 'SRF', 'B5'), ['exists' => 1, 'raw' => 1]));
        $this->assertFalse($patcher->read($bytes, 'SRF', 'C99')['exists']);

        // The name of the sheet is looked for as it is written, then without regard to case and blanks.
        $this->assertSame('y', $patcher->read($bytes, ' lain ', 'B2')['raw']);
        $this->expectException(RuntimeException::class);
        $patcher->read($bytes, 'Tidak ada', 'A1');
    }

    public function test_it_writes_text_so_that_it_stays_text_and_leaves_everything_else_as_it_was(): void
    {
        $patcher = new XlsxPatcher();
        $before = $this->book();

        $after = $patcher->write($before, 'SRF', 'E2', '000456');

        $this->assertNotSame($before, $after);
        $tables = $this->tables($after);
        // The new text is text (a serial number with zeros in front is not turned into a number) ...
        $this->assertSame('000456', $tables['SRF']['rows'][0][4]);
        $this->assertArrayNotHasKey(4, $tables['SRF']['numbers'][0] ?? []);
        // ... and the rest of the sheet and of the other sheet is what it was.
        $kept = $this->tables($before);
        $kept['SRF']['rows'][0][4] = '000456';
        $this->assertSame($kept, $tables);
        // The formula is still a formula.
        $this->assertTrue($patcher->read($after, 'SRF', 'F2')['formula']);
    }

    public function test_it_writes_numbers_and_keeps_the_look_of_the_cell(): void
    {
        $patcher = new XlsxPatcher();
        $before = $this->book();

        // 45300.5 in a cell that is formatted as a date and time.
        $after = $patcher->write($before, 'SRF', 'B3', 45300.5);

        $this->assertSame('09-01-2024 12:00', $this->tables($after)['SRF']['rows'][1][1]);
        $this->assertSame($patcher->read($before, 'SRF', 'B3')['style'], $patcher->read($after, 'SRF', 'B3')['style']);
        $this->assertSame('45300.5', $patcher->read($after, 'SRF', 'B3')['raw']);

        $this->assertSame('7', $patcher->read($patcher->write($before, 'SRF', 'D2', 7.0), 'SRF', 'D2')['raw']);
        $this->assertSame('-12.25', $patcher->read($patcher->write($before, 'SRF', 'D2', -12.25), 'SRF', 'D2')['raw']);
        $this->assertSame('1500000', $patcher->read($patcher->write($before, 'SRF', 'D2', 1500000.0), 'SRF', 'D2')['raw']);
    }

    public function test_it_empties_a_cell_and_fills_an_empty_one(): void
    {
        $patcher = new XlsxPatcher();
        $before = $this->book();

        $emptied = $patcher->write($before, 'SRF', 'C2', null);
        $this->assertNull($patcher->read($emptied, 'SRF', 'C2')['raw']);
        $this->assertSame('', $this->tables($emptied)['SRF']['rows'][0][2]);

        // A cell with no <c> yet in a row that has others: it goes in at its place among them.
        $filled = $patcher->write($before, 'SRF', 'B5', 'catatan & <baru>');
        $this->assertSame('catatan & <baru>', $this->tables($filled)['SRF']['rows'][2][1]);
        $this->assertSame('Citra Dewi', $this->tables($filled)['SRF']['rows'][2][2]);

        // The first cell of a row that has cells further on, and one beyond the last.
        $first = $patcher->write($before, 'SRF', 'A5', 'ICT/SRF/9');
        $this->assertSame('ICT/SRF/9', $this->tables($first)['SRF']['rows'][2][0]);
        $beyond = $patcher->write($before, 'SRF', 'H2', 'jauh');
        $this->assertSame('jauh', $patcher->read($beyond, 'SRF', 'H2')['raw']);
        $this->assertSame('Budi Santoso', $patcher->read($beyond, 'SRF', 'C2')['raw']);
    }

    public function test_it_makes_a_row_that_is_not_there(): void
    {
        $patcher = new XlsxPatcher();
        $before = $this->book();

        // Below the last row, and between rows that are there.
        $below = $patcher->write($before, 'SRF', 'A9', 'baru');
        $this->assertSame('baru', $patcher->read($below, 'SRF', 'A9')['raw']);
        $this->assertSame('ICT/SRF/4', $patcher->read($below, 'SRF', 'A5')['raw']);

        $sheet = self::xlsx(['S' => [['a'], [], ['c']]]);
        $between = $patcher->write($sheet, 'S', 'B2', 'tengah');
        $this->assertSame(['a'], array_slice($this->tables($between)['S']['columns'], 0, 1));
        $this->assertSame('tengah', $patcher->read($between, 'S', 'B2')['raw']);
        $this->assertSame('c', $patcher->read($between, 'S', 'A3')['raw']);
    }

    public function test_every_other_part_of_the_archive_is_carried_over_as_it_was(): void
    {
        $patcher = new XlsxPatcher();
        $before = $this->book();
        $after = $patcher->write($before, 'SRF', 'C2', 'Orang Lain');

        $parts = function (string $bytes): array {
            $file = tempnam(sys_get_temp_dir(), 'xlz');
            file_put_contents($file, $bytes);
            $zip = new ZipArchive();
            $zip->open($file, ZipArchive::RDONLY);
            $parts = [];
            for ($at = 0; $at < $zip->numFiles; $at++) {
                $name = $zip->getNameIndex($at);
                $parts[$name] = $zip->getFromIndex($at);
            }
            $zip->close();
            unlink($file);
            ksort($parts);

            return $parts;
        };
        $was = $parts($before);
        $now = $parts($after);

        $this->assertSame(array_keys($was), array_keys($now));
        // Only the sheet that was written (and the settings of calculation) differ.
        $changed = array_keys(array_filter($now, fn ($content, $name) => $content !== $was[$name], ARRAY_FILTER_USE_BOTH));
        $this->assertContains('xl/worksheets/sheet1.xml', $changed);
        $this->assertNotContains('xl/worksheets/sheet2.xml', $changed);
        $this->assertNotContains('xl/styles.xml', $changed);
        $this->assertLessThanOrEqual(2, count($changed));
    }

    public function test_a_sheet_of_tens_of_thousands_of_rows_is_found_and_written_exactly(): void
    {
        // Rows 1, 3, 5 ... 119999 (even numbers are not there), written by hand: a text that long is what a pattern run over the
        // whole sheet gives up on, and "not found" would have made a second row of a number that is there.
        $rows = '';
        for ($n = 1; $n < 120000; $n += 2) {
            $rows .= "<row r=\"{$n}\" spans=\"1:2\"><c r=\"A{$n}\" t=\"inlineStr\"><is><t>No {$n}</t></is></c><c r=\"B{$n}\"><v>{$n}</v></c></row>";
        }
        $file = tempnam(sys_get_temp_dir(), 'xlbig');
        file_put_contents($file, self::xlsx(['Besar' => [['No', 'Nilai']]]));
        $zip = new ZipArchive();
        $zip->open($file);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $rows . '</sheetData></worksheet>');
        $zip->close();
        $big = (string) file_get_contents($file);
        unlink($file);
        $count = fn (string $bytes) => (function () use ($bytes) {
            $path = tempnam(sys_get_temp_dir(), 'xlc');
            file_put_contents($path, $bytes);
            $archive = new ZipArchive();
            $archive->open($path);
            $xml = (string) $archive->getFromName('xl/worksheets/sheet1.xml');
            $archive->close();
            unlink($path);

            return [substr_count($xml, '<row '), $xml];
        })();

        $patcher = new XlsxPatcher();
        $started = microtime(true);

        // A cell near the end of a row that is there: written once, the row not doubled.
        $changed = $patcher->write($big, 'Besar', 'B119997', 7.0);
        [$rowsNow] = $count($changed);
        $this->assertSame(60000, $rowsNow);
        $this->assertSame('7', $patcher->read($changed, 'Besar', 'B119997')['raw']);
        $this->assertSame('No 119997', $patcher->read($changed, 'Besar', 'A119997')['raw']);

        // A row that is not there, between two that are: made once, where it belongs.
        $made = $patcher->write($big, 'Besar', 'A70000', 'tengah');
        [$rowsNow, $xml] = $count($made);
        $this->assertSame(60001, $rowsNow);
        $this->assertSame('tengah', $patcher->read($made, 'Besar', 'A70000')['raw']);
        $this->assertSame('No 69999', $patcher->read($made, 'Besar', 'A69999')['raw']);
        $this->assertSame('No 70001', $patcher->read($made, 'Besar', 'A70001')['raw']);
        $this->assertTrue(strpos($xml, '<row r="69999"') < strpos($xml, '<row r="70000"') && strpos($xml, '<row r="70000"') < strpos($xml, '<row r="70001"'));

        // Past the last row, and in front of the first.
        $this->assertSame('after', $patcher->read($patcher->write($big, 'Besar', 'A200000', 'after'), 'Besar', 'A200000')['raw']);
        $this->assertSame('first', $patcher->read($patcher->write($big, 'Besar', 'A2', 'first'), 'Besar', 'A2')['raw']);

        $this->assertLessThan(20, microtime(true) - $started, 'it does not take long');
    }

    public function test_what_is_not_a_workbook_or_a_cell_is_refused(): void
    {
        $patcher = new XlsxPatcher();
        $book = $this->book();

        foreach ([
            fn () => $patcher->write('not a workbook', 'SRF', 'A1', 'x'),
            fn () => $patcher->write($book, 'SRF', 'A', 'x'),
            fn () => $patcher->write($book, 'SRF', 'A0', 'x'),
            fn () => $patcher->write($book, 'SRF', 'A1', "bad \x01 character"),
            fn () => $patcher->write($book, 'SRF', 'A1', NAN),
            fn () => $patcher->write($book, 'Tidak ada', 'A1', 'x'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('This should have been refused.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_reader_tells_where_a_column_is_and_which_cells_hold_a_formula(): void
    {
        $tables = $this->tables(self::xlsx(['S' => [
            ['No', null, 'Nama', null, 'Cek'],
            ['1', null, 'a', null, ['formula' => '=A2&"x"']],
            // A formula that gives nothing (yet) is a formula too: it must not be taken for an empty cell and written over.
            ['2', null, 'b', null, ['formula' => '=""']],
            ['3', null, 'c', null, null],
        ]]));

        // Columns A, C and E of Excel: the empty ones between are not columns of the table.
        $this->assertSame([1, 3, 5], $tables['S']['positions']);
        $this->assertSame(['No', 'Nama', 'Cek'], $tables['S']['columns']);
        $this->assertSame([2, 3, 4], $tables['S']['lines']);
        $this->assertSame([0 => [2 => true], 1 => [2 => true]], $tables['S']['formulas']);
        $this->assertSame('1x', $tables['S']['rows'][0][2]);
        $this->assertSame('', $tables['S']['rows'][1][2]);
    }

    public function test_column_letters_and_numbers_are_each_others_inverse(): void
    {
        foreach ([1 => 'A', 26 => 'Z', 27 => 'AA', 52 => 'AZ', 53 => 'BA', 702 => 'ZZ', 703 => 'AAA'] as $number => $letters) {
            $this->assertSame($letters, XlsxPatcher::columnLetters($number));
            $this->assertSame($number, XlsxPatcher::columnNumber($letters));
        }
    }
}
