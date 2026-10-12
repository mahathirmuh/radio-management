<?php

namespace Tests\Unit;

use Generator;
use LogicException;
use Modules\SharePointDocs\Services\GraphException;
use Modules\SharePointDocs\Services\WorkbookReader;
use Modules\SharePointDocs\Services\XlsxReader;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\CutsAPartShort;
use TypeError;
use ZipArchive;

/**
 * The reading of an .xlsx workbook, on workbooks written out by hand here: the forms a cell can
 * take in the file that Excel and other programs use but the workbooks of the feature tests
 * (written by PhpSpreadsheet) do not contain, and files that are too big, broken or hostile.
 */
class WorkbookReaderTest extends TestCase
{
    private const MAIN = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    private const RELATIONS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

    /** The folder of this test where the reader makes its temporary copy of a workbook: nothing else of the machine is looked at or touched. */
    private static string $folder = '';

    protected function setUp(): void
    {
        parent::setUp();

        self::$folder = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wbtest-' . bin2hex(random_bytes(4));
        mkdir(self::$folder);
    }

    protected function tearDown(): void
    {
        foreach (glob(self::$folder . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir(self::$folder);

        parent::tearDown();
    }

    /** The bytes of a workbook with these sheets (name => the XML inside <sheetData>) and, where given, other parts instead of the usual ones. */
    private static function workbook(array $sheets, array $parts = []): string
    {
        $list = '';
        $relations = '';
        $files = [];
        foreach (array_keys($sheets) as $at => $name) {
            $number = $at + 1;
            $list .= sprintf('<sheet name="%s" sheetId="%d" r:id="rId%d"/>', htmlspecialchars((string) $name), $number, $number);
            $relations .= sprintf('<Relationship Id="rId%d" Type="%s/worksheet" Target="worksheets/sheet%d.xml"/>', $number, self::RELATIONS, $number);
            $files["xl/worksheets/sheet{$number}.xml"] = '<worksheet ' . self::MAIN . '><sheetData>' . $sheets[$name] . '</sheetData></worksheet>';
        }
        $files += [
            'xl/workbook.xml' => '<workbook ' . self::MAIN . "><sheets>{$list}</sheets></workbook>",
            'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relations . '</Relationships>',
        ];

        return self::zip(array_map(fn (string $content) => self::XML . $content, $parts + $files));
    }

    /** The bytes of a zip archive with these files. */
    private static function zip(array $files): string
    {
        $file = tempnam(sys_get_temp_dir(), 'wbt');
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        try {
            return (string) file_get_contents($file);
        } finally {
            unlink($file);
        }
    }

    private static function read(string $bytes, bool $skipUnused = false, int $maxCells = XlsxReader::MAX_CELLS, float $seconds = 15.0, ?XlsxReader $xlsx = null): array
    {
        return (new WorkbookReader($xlsx ?? new XlsxReader(), self::$folder))->read($bytes, $skipUnused, $maxCells, $seconds);
    }

    /** The cells of every sheet of these bytes as the XlsxReader gives them, with its own limits (a reader of which some are lowered, say). */
    private static function sheets(string $bytes, int $maxRows = 20010, ?XlsxReader $xlsx = null, float $seconds = 15.0): array
    {
        $file = self::$folder . DIRECTORY_SEPARATOR . 'direct.xlsx';
        file_put_contents($file, $bytes);

        return iterator_to_array(($xlsx ?? new XlsxReader())->sheets($file, $maxRows, WorkbookReader::MAX_COLUMNS, XlsxReader::MAX_CELLS, $seconds), false);
    }

    private static function reason(callable $reading): string
    {
        try {
            $reading();
        } catch (GraphException $e) {
            return $e->reason;
        }

        return 'read';
    }

    /**
     * Where the data of a part begins in the bytes of a zip: after its local header, which is found by its signature and the name that follows
     * (the name also stands in the plain text of small parts that are stored, and in the directory at the end).
     */
    private static function dataStart(string $zip, string $name): int
    {
        for ($at = strpos($zip, $name); $at !== false; $at = strpos($zip, $name, $at + 1)) {
            $header = $at - 30;
            if ($header >= 0 && substr($zip, $header, 4) === "PK\x03\x04" && unpack('v', substr($zip, $header + 26, 2))[1] === strlen($name)) {
                return $header + 30 + strlen($name) + unpack('v', substr($zip, $header + 28, 2))[1];
            }
        }

        throw new LogicException("No local header of {$name} in the archive.");
    }

    /** @return array<int, string> the temporary copies of a workbook that are lying around (in the folder of this test) */
    private static function leftovers(): array
    {
        return glob(self::$folder . DIRECTORY_SEPARATOR . 'wb*') ?: [];
    }

    private static function cell(string $at, string $text): string
    {
        return "<c r=\"{$at}\" t=\"inlineStr\"><is><t>{$text}</t></is></c>";
    }

    public function test_every_form_a_cell_takes_in_the_file_is_read_as_the_text_excel_shows(): void
    {
        $main = self::MAIN;
        $relations = self::RELATIONS;
        $bytes = self::workbook([], [
            // Dates count from 1904 (a workbook first made on a Mac); one sheet is a chart, one is "very hidden".
            // The extension element at the end is how Excel saves the file: it has the name of the one before it.
            'xl/workbook.xml' => "<workbook {$main}><workbookPr date1904=\"1\"/><sheets>"
                . '<sheet name="Daftar" sheetId="1" r:id="rId1"/><sheet name="Grafik" sheetId="2" r:id="rId2"/><sheet name="Rahasia" sheetId="3" state="veryHidden" r:id="rId3"/>'
                . '</sheets><extLst><ext uri="{140A7094-0E35-4892-8432-C4D2E57EDEB5}" xmlns:x15="http://schemas.microsoft.com/office/spreadsheetml/2010/11/main"><x15:workbookPr chartTrackingRefBase="1"/></ext></extLst></workbook>',
            // The parts are where the relations say, not where they usually are.
            'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . "<Relationship Id=\"rId1\" Type=\"{$relations}/worksheet\" Target=\"lembar/satu.xml\"/>"
                . "<Relationship Id=\"rId2\" Type=\"{$relations}/chartsheet\" Target=\"chartsheets/sheet1.xml\"/>"
                . "<Relationship Id=\"rId3\" Type=\"{$relations}/worksheet\" Target=\"/xl/lembar/tiga.xml\"/>"
                . "<Relationship Id=\"rId4\" Type=\"{$relations}/styles\" Target=\"gaya.xml\"/>"
                . "<Relationship Id=\"rId5\" Type=\"{$relations}/sharedStrings\" Target=\"teks.xml\"/>"
                . "<Relationship Id=\"rId6\" Type=\"{$relations}/externalLink\" Target=\"https://example.com/lain.xlsx\" TargetMode=\"External\"/>"
                . '</Relationships>',
            // Style 1: a built-in date, 2: a date format of the workbook's own, 3: thousands, 4: per cent, 5: a time of day. (The named styles are not what cells point to.)
            'xl/gaya.xml' => "<styleSheet {$main}><numFmts count=\"1\"><numFmt numFmtId=\"164\" formatCode=\"dd\\-mmm\\-yy\"/></numFmts>"
                . '<cellStyleXfs count="2"><xf numFmtId="14"/><xf numFmtId="14"/></cellStyleXfs>'
                . '<cellXfs count="6"><xf numFmtId="0"/><xf numFmtId="14"/><xf numFmtId="164"/><xf numFmtId="3"/><xf numFmtId="10"/><xf numFmtId="20"/></cellXfs></styleSheet>',
            // 1: text in pieces with a reading aid, 2: a line break the way Excel writes it, 3: blanks only.
            'xl/teks.xml' => "<sst {$main}><si><t>No</t></si>"
                . '<si><r><t>Na</t></r><r><rPr><b/></rPr><t xml:space="preserve">ma </t></r><rPh sb="0" eb="1"><t>bukan ini</t></rPh></si>'
                . '<si><t>Baris_x000D__x000A_dua</t></si><si><t xml:space="preserve">   </t></si></sst>',
            'xl/lembar/satu.xml' => "<worksheet {$main}><sheetData>"
                . '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="inlineStr"><is><t>Tanggal</t></is></c>'
                . '<c r="D1" t="inlineStr"><is><r><t>Ni</t></r><r><t>lai</t></r></is></c><c r="E1" t="inlineStr"><is><t>Ket</t></is></c></row>'
                . '<row r="2"><c r="A2"><v>1</v></c><c r="B2" t="s"><v>2</v></c><c r="C2" s="1"><v>44197.5</v></c><c r="D2" s="3"><v>1234567.891</v></c>'
                . '<c r="E2" t="str"><f>"hasil " &amp; "rumus"</f><v>hasil rumus</v></c><c r="H2" s="5"><v>0.5</v></c></row>'
                // A row and cells that do not say where they are: the next row, the next columns.
                . '<row><c><v>2</v></c><c t="inlineStr"><is><t>Tanpa alamat</t></is></c><c s="2"><v>44198</v></c><c s="4"><v>0.125</v></c><c t="b"><v>1</v></c></row>'
                . '<row r="5"><c r="A5" t="e"><f>NA()</f><v>#N/A</v></c><c r="B5" t="s"><v>3</v></c><c r="C5" t="d"><v>2026-10-06T09:30:00</v></c>'
                . '<c r="D5"><f>1/0</f></c><c r="E5" t="b"><v>0</v></c><c r="F5" t="s"></c><c r="G5" t="inlineStr"><is><t>jauh</t></is></c></row>'
                . '</sheetData></worksheet>',
            'xl/lembar/tiga.xml' => "<worksheet {$main}><sheetData><row r=\"1\"><c r=\"A1\" t=\"inlineStr\"><is><t>Sandi</t></is></c></row></sheetData></worksheet>",
        ]);

        $tables = self::read($bytes);

        $this->assertSame(['Daftar', 'Rahasia'], array_column($tables, 'name'), 'A chart is not a sheet of cells.');
        $this->assertSame([false, true], array_column($tables, 'hidden'));
        $this->assertSame(['Sandi'], $tables[1]['columns'], 'The part that an absolute target names is the one that is read.');
        $this->assertSame(['No', 'Nama', 'Tanggal', 'Nilai', 'Ket', 'Kolom G', 'Kolom H'], $tables[0]['columns']);
        $this->assertSame([2, 3, 5], $tables[0]['lines']);
        $this->assertSame([
            ['1', 'Baris dua', '02-01-2025 12:00', '1.234.568', 'hasil rumus', '', '12:00'],
            ['2', 'Tanpa alamat', '03-01-2025', '12,50%', 'TRUE', '', ''],
            ['#N/A', '', '06-10-2026 09:30', '', 'FALSE', 'jauh', ''],
        ], $tables[0]['rows']);
        // The numbers behind the cells, for sorting: dates as Excel counts days from 1900; a time of day has no date to move.
        $this->assertSame([0 => 1.0, 2 => 45659.5, 3 => 1234567.891, 6 => 0.5], $tables[0]['numbers'][0]);
        $this->assertSame([0 => 2.0, 2 => 45660.0, 3 => 0.125], $tables[0]['numbers'][1]);
        $this->assertSame([2], array_keys($tables[0]['numbers'][2]));
        $this->assertEqualsWithDelta(46301 + 9.5 / 24, $tables[0]['numbers'][2][2], 0.0001);
        $this->assertFalse($tables[0]['truncated']);
    }

    public function test_a_number_is_written_with_a_decimal_comma_whatever_its_format(): void
    {
        // [format code, the value, what Excel shows, with the separators of this application]
        $cases = [
            ['0.00', 1234.5, '1234,50'],
            ['0.0', 1234.5, '1234,5'],
            ['0.000', 1.234, '1,234'],
            ['0.00', -1234.5, '-1234,50'],
            // The exponent has as many digits as the format has zeros after its sign, as in Excel.
            ['0.00E+00', 0.00012, '1,20E-04'],
            ['0.00E+00', 123456789, '1,23E+08'],
            ['0.0E+000', 123456789, '1,2E+008'],
            ['0.00%', 0.125, '12,50%'],
            ['#,##0.00%', 12.3456, '1234,56%'],
            ['"Rp "0.00', 1234.5, 'Rp 1234,50'],
            ['[$USD] 0.00', 1234.5, 'USD 1234,50'],
            ['0.00_);(0.00)', -5.5, '(5,50)'],
            ['#,##0.00', 1234567.891, '1.234.567,89'],
            ['#,##0.0', 1234567.5, '1.234.567,5'],
            ['#,##0', 1234.5, '1.235'],
            ['0%', 0.125, '13%'],
            ['0', 1234.5, '1235'],
            ['0000-0000', 12345678, '1234-5678'],
            ['"1.0 "0', 5, '1.0 5'], // a point in a text is not the number's
            ['General', 1234.5, '1234,5'],
            ['General', 1234567, '1234567'],
            ['General', '0.00012', '0,00012'],
            // Very small and very large numbers as Excel writes them: not "0", and not digits that are not in the file.
            ['General', '1.5E-12', '1,5E-12'],
            ['General', '0.00000123', '0,00000123'],
            ['General', '0.000000123', '1,23E-07'],
            ['General', '1.2345678901234567E+19', '1,23456789012346E+19'],
            ['General', '-2E+15', '-2E+15'],
            // A length of time is hours, not a date in 1900.
            ['[h]:mm', 1.5, '36:00'],
            ['[h]:mm:ss', 4.1666667, '100:00:00'],
            ['h:mm', 0.5, '12:00'],
        ];
        $formats = '';
        $xfs = '<xf numFmtId="0"/>';
        $titles = '';
        $values = '';
        foreach ($cases as $at => [$code, $value]) {
            $formats .= sprintf('<numFmt numFmtId="%d" formatCode="%s"/>', 164 + $at, htmlspecialchars($code));
            $xfs .= sprintf('<xf numFmtId="%d"/>', 164 + $at);
            $column = Coordinate::stringFromColumnIndex($at + 1);
            $titles .= self::cell("{$column}1", "F{$at}");
            $values .= sprintf('<c r="%s2" s="%d"><v>%s</v></c>', $column, $at + 1, $value);
        }
        $main = self::MAIN;
        $bytes = self::workbook(['Format' => "<row r=\"1\">{$titles}</row><row r=\"2\">{$values}</row>"], [
            'xl/styles.xml' => "<styleSheet {$main}><numFmts count=\"" . count($cases) . "\">{$formats}</numFmts><cellXfs count=\"" . (count($cases) + 1) . "\">{$xfs}</cellXfs></styleSheet>",
        ]);

        $shown = self::read($bytes)[0]['rows'][0];

        foreach ($cases as $at => [$code, $value, $expected]) {
            $this->assertSame($expected, $shown[$at], "{$code} of {$value}");
        }
    }

    public function test_a_web_address_keeps_the_blanks_it_was_typed_with_and_any_other_text_has_them_collapsed(): void
    {
        $nbsp = "\u{00A0}";
        $bytes = self::workbook(['Daftar' =>
            '<row r="1">' . self::cell('A1', 'Nama') . self::cell('B1', 'Tautan') . '</row>'
            // Text: every run of blanks, a tab and a line break is one space, and the ends are trimmed.
            . '<row r="2">' . self::cell('A2', "  Andi   \t  Wijaya\nLestari  ") . self::cell('B2', 'https://contoso.sharepoint.com/sites/Team/ICTCOF2 - Andi  Wijaya.pdf') . '</row>'
            // An address: its blanks stay (a file of that name is looked for, letter for letter); the ends are trimmed; the scheme may be in capitals.
            . '<row r="3">' . self::cell('A3', "a{$nbsp}{$nbsp}b") . self::cell('B3', "  HTTPS://contoso.sharepoint.com/a/b  c.pdf\n") . '</row>'
            // A line break inside it is a space like in any other cell, with the blanks next to it; a no-break space stays.
            . '<row r="4">' . self::cell('A4', 'http://contoso.sharepoint.com/a  b.pdf') . self::cell('B4', "https://contoso.sharepoint.com/a \n  b.pdf") . '</row>'
            . '<row r="5">' . self::cell('A5', 'tidak https://contoso.sharepoint.com/a  b.pdf') . self::cell('B5', "https://contoso.sharepoint.com/a{$nbsp}{$nbsp}b.pdf") . '</row>'
            // No-break spaces at its ends are blanks at its ends too.
            . '<row r="6">' . self::cell('A6', "{$nbsp}x{$nbsp}") . self::cell('B6', "{$nbsp}https://contoso.sharepoint.com/a.pdf{$nbsp}") . '</row>'
            // Up to 2048 bytes it is an address (its blanks stay); a longer cell is no address, and is text like any other.
            . '<row r="7">' . self::cell('A7', 'edge') . self::cell('B7', $edge = 'https://x.y/' . str_repeat('a', 2033) . '  b') . '</row>'
            . '<row r="8">' . self::cell('A8', 'over') . self::cell('B8', 'https://x.y/' . str_repeat('a', 2034) . '  b') . '</row>'
            . '<row r="9">' . self::cell('A9', 'blanks') . self::cell('B9', 'https://x.y/' . str_repeat(' ', 40000) . 'z.pdf') . '</row>'
            // The length is the bytes of the cell, not its characters (the limit is for how slow the reading can get).
            . '<row r="10">' . self::cell('A10', 'bytes') . self::cell('B10', 'https://x.y/' . str_repeat('é', 1100) . '  b') . '</row>',
        ]);

        $rows = self::read($bytes)[0]['rows'];

        $this->assertSame(['Andi Wijaya Lestari', 'https://contoso.sharepoint.com/sites/Team/ICTCOF2 - Andi  Wijaya.pdf'], $rows[0]);
        $this->assertSame(['a b', 'HTTPS://contoso.sharepoint.com/a/b  c.pdf'], $rows[1]);
        $this->assertSame(['http://contoso.sharepoint.com/a b.pdf', 'https://contoso.sharepoint.com/a b.pdf'], $rows[2], 'only an https address is kept as it was, and a line break is a space even there');
        $this->assertSame(['tidak https://contoso.sharepoint.com/a b.pdf', "https://contoso.sharepoint.com/a{$nbsp}{$nbsp}b.pdf"], $rows[3]);
        $this->assertSame(['x', 'https://contoso.sharepoint.com/a.pdf'], $rows[4]);
        $this->assertSame(2048, strlen($edge));
        $this->assertSame($edge, $rows[5][1]);
        $this->assertSame('https://x.y/' . str_repeat('a', 2034) . ' b', $rows[6][1]);
        $this->assertSame('https://x.y/ z.pdf', $rows[7][1], 'a long run of blanks is one blank, and is read at once');
        $this->assertSame('https://x.y/' . str_repeat('é', 1100) . ' b', $rows[8][1], 'two bytes a character: more than 2048 bytes, fewer characters');
    }

    public function test_the_separators_of_the_library_are_put_back_and_no_copy_of_the_workbook_stays(): void
    {
        // Whatever the library had before (here two marks no locale has) is what it has after, in every way a reading ends.
        [$decimal, $thousands] = [StringHelper::getDecimalSeparator(), StringHelper::getThousandsSeparator()];
        StringHelper::setDecimalSeparator('§');
        StringHelper::setThousandsSeparator('¤');
        try {
            $before = self::leftovers();
            $ok = self::workbook(['A' => self::cell('A1', 'x') . self::cell('B1', 'y') . self::cell('C1', 'z')]);

            self::read($ok);
            $this->assertSame(['§', '¤'], [StringHelper::getDecimalSeparator(), StringHelper::getThousandsSeparator()]);
            $this->assertSame($before, self::leftovers());

            // Also when the reading fails, early (not a workbook) or half way (broken XML, too many cells, no time).
            $many = '<row>' . str_repeat('<c><v>1</v></c>', 10) . '</row>';
            $failures = [
                'a zip without a workbook' => [self::zip(['catatan.txt' => 'bukan workbook']), 100, 15.0, GraphException::UNSUPPORTED],
                'a sheet that breaks off' => [self::workbook(['Rusak' => '<row r="1"><c r="A1"><v>1</v></c></row><row r="2"><c r="A2"><v>2</v>']), 100, 15.0, GraphException::UNSUPPORTED],
                'more cells than allowed' => [self::workbook(['Banyak' => $many]), 5, 15.0, GraphException::TOO_LARGE],
                'no time' => [self::workbook(['Lama' => str_repeat($many, 1000)]), 100_000, 0.0, GraphException::TOO_SLOW],
            ];
            foreach ($failures as $case => [$bytes, $maxCells, $seconds, $reason]) {
                $this->assertSame($reason, self::reason(fn () => self::read($bytes, false, $maxCells, $seconds)), $case);
                $this->assertSame(['§', '¤'], [StringHelper::getDecimalSeparator(), StringHelper::getThousandsSeparator()], $case);
                $this->assertSame($before, self::leftovers(), "{$case}: the temporary copy is removed");
            }
        } finally {
            StringHelper::setDecimalSeparator($decimal);
            StringHelper::setThousandsSeparator($thousands);
        }
    }

    public function test_the_copies_a_reading_that_did_not_end_left_behind_are_swept_away_when_they_are_old(): void
    {
        $workbook = self::workbook(['A' => self::cell('A1', 'x') . self::cell('B1', 'y') . self::cell('C1', 'z')]);
        $make = function (string $name, int $secondsAgo): string {
            $file = self::$folder . DIRECTORY_SEPARATOR . $name;
            file_put_contents($file, 'leftover');
            touch($file, time() - $secondsAgo);

            return $file;
        };
        [$old, $recent, $other] = [$make('wbOLD', 2 * 3600), $make('wbRECENT', 60), $make('notes.txt', 2 * 3600)];

        self::read($workbook);

        $this->assertFileDoesNotExist($old, 'A copy from two hours ago is the leftover of a reading that did not end.');
        $this->assertFileExists($recent, 'A copy of a minute ago may be that of a reading that is going on.');
        $this->assertFileExists($other, 'Only what the reader made is its to remove.');

        // Where its own folder cannot be made it works in the temporary folder of the system, which it shares with
        // other programs: nothing in there is swept away, however old and whatever it is called.
        $foreign = tempnam(sys_get_temp_dir(), 'wbforeign');
        try {
            touch($foreign, time() - 2 * 3600);
            $nowhere = self::$folder . DIRECTORY_SEPARATOR . 'notes.txt' . DIRECTORY_SEPARATOR . 'inside'; // below a file: it cannot be a folder
            $this->assertSame([['A', 'x']], array_map(fn (array $table) => [$table['name'], $table['columns'][0]], (new WorkbookReader(new XlsxReader(), $nowhere))->read($workbook)));
            $this->assertFileExists($foreign);
        } finally {
            @unlink($foreign);
        }
    }

    public function test_a_reading_that_is_cut_off_leaves_no_copy_of_the_workbook_behind(): void
    {
        // "finally" does not run after a fatal error (out of memory, out of time): the copy has to go from the function for the end of the
        // script, and on Windows a file that is still open cannot be removed, so the archive is let go of first. (An exit takes the generator
        // with it, which closes the archive by itself; only a fatal error leaves it open for the shutdown function to deal with.)
        $bytes = self::$folder . DIRECTORY_SEPARATOR . 'book.xlsx';
        file_put_contents($bytes, self::workbook(['A' => self::cell('A1', 'x') . self::cell('B1', 'y') . self::cell('C1', 'z')]));

        foreach ([
            'an exit' => ['exit(7);', 7],
            // The memory runs out: a fatal error that ends the script at once, without unwinding anything.
            'a fatal error' => ["ini_set('memory_limit', '32M'); \$big = str_repeat('x', 256 * 1024 * 1024);", 255],
        ] as $how => [$cut, $expected]) {
            $script = self::$folder . DIRECTORY_SEPARATOR . 'cut.php';
            file_put_contents($script, '<?php
                require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';
                $cutOff = new class extends Modules\SharePointDocs\Services\XlsxReader
                {
                    public function sheets(string $file, int $maxRows, int $maxColumns, int $maxCells = self::MAX_CELLS, float $seconds = 15.0): Generator
                    {
                        foreach (parent::sheets($file, $maxRows, $maxColumns, $maxCells, $seconds) as $sheet) {
                            ' . $cut . ' // after the first sheet, with the archive still open
                        }
                    }
                };
                (new Modules\SharePointDocs\Services\WorkbookReader($cutOff, $argv[1]))->read(file_get_contents($argv[2]));
            ');

            $process = proc_open([PHP_BINARY, $script, self::$folder, $bytes], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            $code = proc_close($process);

            $this->assertSame($expected, $code, "{$how}: {$output}");
            $this->assertSame([], self::leftovers(), "{$how}: no copy of the workbook is left");
        }
    }

    public function test_rows_that_hold_only_their_number_yet_can_be_left_out(): void
    {
        $formula = fn (string $at, string $text) => "<c r=\"{$at}\" t=\"str\"><f>X1</f><v>{$text}</v></c>";
        $bytes = self::workbook(['Register' => ''
            . '<row r="1">' . self::cell('A1', 'No') . self::cell('B1', 'Tanggal') . self::cell('C1', 'Nama') . self::cell('D1', 'Tautan') . '</row>'
            . '<row r="2">' . self::cell('A2', 'REG/1') . '<c r="B2"><v>45000</v></c>' . self::cell('C2', 'Budi Santoso') . $formula('D2', 'https://example.com/REG1') . '</row>'
            // No date yet, but a name: this number is in use.
            . '<row r="3">' . self::cell('A3', 'REG/2') . self::cell('C3', 'Andi Wijaya') . $formula('D3', 'https://example.com/REG2') . '</row>'
            // Numbered ahead: the number typed in, the rest worked out from it.
            . '<row r="4">' . self::cell('A4', 'REG/3') . $formula('D4', 'https://example.com/REG3') . $formula('E4', 'hanya di baris kosong') . '</row>'
            // Not even the number is typed in.
            . '<row r="5">' . $formula('A5', 'REG/4') . $formula('D5', 'https://example.com/REG4') . '</row>'
            . '<row r="6">' . self::cell('A6', 'REG/5') . '</row>'
            // Further down, a row that is in use again.
            . '<row r="7">' . self::cell('A7', 'REG/6') . self::cell('C7', 'Citra Dewi') . '</row>',
        ]);

        $all = self::read($bytes)[0];
        $this->assertSame([2, 3, 4, 5, 6, 7], $all['lines']);
        $this->assertSame(['REG/4', '', '', 'https://example.com/REG4', ''], $all['rows'][3]);
        $this->assertSame(['No', 'Tanggal', 'Nama', 'Tautan', 'Kolom E'], $all['columns']);

        $used = self::read($bytes, true)[0];
        $this->assertSame([2, 3, 7], $used['lines'], 'The rows keep the numbers they have in Excel.');
        // A column without a title that only the rows left out use is not a column of the table either.
        $this->assertSame(['No', 'Tanggal', 'Nama', 'Tautan'], $used['columns']);
        $this->assertSame(['REG/2', '', 'Andi Wijaya', 'https://example.com/REG2'], $used['rows'][1]);
        $this->assertSame([0 => [1 => 45000.0]], $used['numbers']);

        // A sheet that is one column of entries has no register number to tell apart: nothing is said about it here,
        // the choice is made per workbook in the config.
        $list = self::workbook(['Daftar' => '<row r="1">' . self::cell('A1', 'Nama') . '</row><row r="2">' . self::cell('A2', 'Budi') . '</row>']);
        $this->assertSame([2], self::read($list)[0]['lines']);
    }

    public function test_a_sheet_longer_or_wider_than_is_read_is_cut_off_and_says_so_when_something_is_lost(): void
    {
        $rows = '<row r="1"><c r="A1" t="inlineStr"><is><t>No</t></is></c></row>';
        for ($row = 2; $row <= WorkbookReader::MAX_ROWS + 15; $row++) {
            $rows .= "<row r=\"{$row}\"><c r=\"A{$row}\"><v>{$row}</v></c></row>";
        }
        $long = self::read(self::workbook(['Panjang' => $rows, 'Pendek' => '<row r="1"><c r="A1"><v>1</v></c></row><row r="2"><c r="A2"><v>2</v></c></row>']));

        $this->assertTrue($long[0]['truncated']);
        $this->assertSame(WorkbookReader::MAX_ROWS + 10, end($long[0]['lines']));
        $this->assertFalse($long[1]['truncated'], 'What is cut off is said per sheet.');
        $this->assertSame([2], $long[1]['lines']);

        // Rows past the last one that is read, that hold nothing (a row height, a fill colour), lose nothing.
        $limit = WorkbookReader::MAX_ROWS + 10;
        $head = '<row r="1">' . self::cell('A1', 'No') . '</row><row r="2"><c r="A2"><v>2</v></c></row>';
        $tail = fn (string $rest) => self::read(self::workbook(['Ekor' => $head . $rest]))[0]['truncated'];
        $this->assertFalse($tail('<row r="' . ($limit + 5) . '" ht="18" customHeight="1"/>'), 'an empty row');
        $this->assertFalse($tail('<row r="' . ($limit + 5) . '"><c r="A' . ($limit + 5) . '" s="3"/><c r="B' . ($limit + 5) . '"><f>1+1</f></c></row>'), 'cells with a format, and a formula without a value');
        $this->assertTrue($tail('<row r="' . ($limit + 5) . '" ht="18"/><row r="' . ($limit + 6) . '"><c r="A' . ($limit + 6) . '"><v>7</v></c></row>'), 'a value further down');
        $emptyTail = '';
        for ($row = $limit + 1; $row <= $limit + 40; $row++) {
            $emptyTail .= "<row r=\"{$row}\">" . str_repeat('<c s="3"/>', 200) . '</row>';
        }
        $this->assertFalse($tail($emptyTail), 'a long tail that only carries a format is looked at for a while, then taken for empty');

        // Exactly as wide as is read: nothing is cut off. One column more: said so only when it holds something.
        $last = Coordinate::stringFromColumnIndex(WorkbookReader::MAX_COLUMNS);
        $beyond = Coordinate::stringFromColumnIndex(WorkbookReader::MAX_COLUMNS + 1);
        $titles = '<row r="1">' . self::cell('A1', 'No') . self::cell("{$last}1", 'Akhir');
        $this->assertFalse(self::read(self::workbook(['Pas' => $titles . "<c r=\"{$beyond}1\"/></row>"]))[0]['truncated']);
        $wide = self::read(self::workbook(['Lebar' => $titles . "<c r=\"{$beyond}1\"><v>1</v></c></row>"]))[0];
        $this->assertTrue($wide['truncated']);
        $this->assertSame(['No', 'Akhir'], $wide['columns']);
    }

    public function test_the_parts_of_the_workbook_are_found_the_way_other_programs_name_them(): void
    {
        $main = self::MAIN;
        $relations = self::RELATIONS;
        $sheet = fn (string $text) => "<worksheet {$main}><sheetData><row r=\"1\">" . self::cell('A1', $text) . '</row></sheetData></worksheet>';
        $bytes = self::workbook([], [
            'xl/workbook.xml' => "<workbook {$main}><sheets><sheet name=\"Satu\" sheetId=\"1\" r:id=\"rId1\"/><sheet name=\"Dua\" sheetId=\"2\" r:id=\"rId2\"/><sheet name=\"Tiga\" sheetId=\"3\" r:id=\"rId3\"/></sheets></workbook>",
            'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . "<Relationship Id=\"rId1\" Type=\"{$relations}/worksheet\" Target=\"./lembar/satu.xml\"/>"
                . "<Relationship Id=\"rId2\" Type=\"{$relations}/worksheet\" Target=\"../xl/lembar/dua.xml\"/>"
                . "<Relationship Id=\"rId3\" Type=\"{$relations}/worksheet\" Target=\"LEMBAR/Tiga.XML\"/>"
                . "<Relationship Id=\"rId4\" Type=\"{$relations}/sharedStrings\" Target=\"Teks.xml\"/>"
                . '</Relationships>',
            'xl/lembar/satu.xml' => "<worksheet {$main}><sheetData><row r=\"1\">" . self::cell('A1', 'satu') . '</row><row r="2"><c r="A2" t="s"><v>0</v></c></row></sheetData></worksheet>',
            'xl/lembar/dua.xml' => $sheet('dua'),
            'xl/lembar/tiga.xml' => $sheet('tiga'),
            // (the file has it as teks.xml, the relationship as Teks.xml)
            'xl/teks.xml' => "<sst {$main}><si><t>dari teks bersama</t></si></sst>",
        ]);
        $tables = self::read($bytes);
        $this->assertSame([['satu'], ['dua'], ['tiga']], array_column($tables, 'columns'));
        $this->assertSame([['dari teks bersama']], $tables[0]['rows']);

        // A part that the workbook names but the file does not have is no empty sheet: the file is not what it says.
        $missing = self::workbook([], [
            'xl/workbook.xml' => "<workbook {$main}><sheets><sheet name=\"Hilang\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>",
            'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . "<Relationship Id=\"rId1\" Type=\"{$relations}/worksheet\" Target=\"worksheets/tidak-ada.xml\"/></Relationships>",
        ]);
        $this->assertSame(GraphException::UNSUPPORTED, self::reason(fn () => self::read($missing)));
    }

    public function test_a_part_whose_root_element_is_empty_is_a_whole_document_and_not_one_that_was_cut_off(): void
    {
        // Programs that have nothing to put in a part write just its root element: no texts, no styles, a sheet without data.
        $main = self::MAIN;
        $bytes = self::workbook(['Satu' => '<row r="1">' . self::cell('A1', 'a') . self::cell('B1', 'b') . self::cell('C1', 'c') . '</row>'], [
            'xl/sharedStrings.xml' => "<sst {$main} count=\"0\" uniqueCount=\"0\"/>",
            'xl/styles.xml' => "<styleSheet {$main}/>",
        ]);
        $this->assertSame([['a', 'b', 'c']], array_column(self::read($bytes), 'columns'));

        $nothing = self::workbook([], [
            'xl/workbook.xml' => "<workbook {$main}><sheets><sheet name=\"Kosong\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>",
            'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="' . self::RELATIONS . '/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml' => "<worksheet {$main}/>",
        ]);
        $tables = self::read($nothing);
        $this->assertSame(['Kosong'], array_column($tables, 'name'));
        $this->assertSame([], $tables[0]['rows']);
    }

    public function test_what_is_not_a_workbook_is_told_from_what_is_a_broken_or_too_big_one(): void
    {
        // What something in between sends instead of the file: the file was not had, and may be had again.
        $this->assertSame(GraphException::UNAVAILABLE, self::reason(fn () => self::read('<html>Sign in</html>')));
        $this->assertSame(GraphException::UNAVAILABLE, self::reason(fn () => self::read('')));
        // A workbook saved with a password is an old-style container, which is a fact about the file.
        $this->assertSame(GraphException::UNSUPPORTED, self::reason(fn () => self::read("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\0", 200))));
        // A zip that is not a workbook, or no zip behind a zip's first bytes, and XML that breaks off.
        $this->assertSame(GraphException::UNSUPPORTED, self::reason(fn () => self::read("PK\x03\x04" . str_repeat('x', 200))));
        $this->assertSame(GraphException::UNSUPPORTED, self::reason(fn () => self::read(self::zip(['catatan.txt' => 'bukan workbook']))));
        $stops = self::workbook(['Rusak' => ''], [
            'xl/worksheets/sheet1.xml' => '<worksheet ' . self::MAIN . '><sheetData><row r="1"><c r="A1"><v>1</v></c></row><row r="2"><c r="A2"><v>2</v>',
        ]);
        $this->assertSame(GraphException::UNSUPPORTED, self::reason(fn () => self::read($stops)), 'A sheet that stops in the middle is not shown as far as it goes.');
        $mismatch = self::workbook(['Rusak' => '<row r="1"><c r="A1"><v>1</v></c></row><row r="2"><c r="A2"><v>2</v>']);
        $this->assertSame(GraphException::UNSUPPORTED, self::reason(fn () => self::read($mismatch)), 'Nor is one whose tags do not match.');

        // A fault of this code is no verdict on the workbook: it is "unavailable" and the log says what it was.
        $faulty = new class extends XlsxReader
        {
            public function sheets(string $file, int $maxRows, int $maxColumns, int $maxCells = self::MAX_CELLS, float $seconds = 15.0): Generator
            {
                throw new TypeError('boom');
            }
        };
        try {
            (new WorkbookReader($faulty))->read(self::workbook(['A' => self::cell('A1', 'x')]));
            $this->fail('A fault is not swallowed.');
        } catch (GraphException $e) {
            $this->assertSame(GraphException::UNAVAILABLE, $e->reason);
            $this->assertStringContainsString('TypeError: boom', $e->getMessage());
        }
    }

    public function test_a_workbook_cannot_use_xml_to_make_itself_bigger_or_to_read_the_server(): void
    {
        // A document type declaration (entities that grow, entities that read a file) is how XML does that: no workbook has one.
        $secret = tempnam(sys_get_temp_dir(), 'wbs');
        file_put_contents($secret, 'ISI-FILE-SERVER');
        try {
            $address = 'file:///' . ltrim(str_replace('\\', '/', $secret), '/');
            $main = self::MAIN;
            $hostile = [
                'a sheet' => ['xl/worksheets/sheet1.xml', "<!DOCTYPE worksheet [<!ENTITY bocor SYSTEM \"{$address}\"><!ENTITY banyak \"" . str_repeat('x', 1000) . '">]>'
                    . "<worksheet {$main}><sheetData><row r=\"1\"><c r=\"A1\" t=\"inlineStr\"><is><t>Judul</t></is></c><c r=\"B1\" t=\"inlineStr\"><is><t>Isi</t></is></c></row>"
                    . '<row r="2"><c r="A2" t="inlineStr"><is><t>a&bocor;z&banyak;</t></is></c><c r="B2" t="str"><v>&bocor;</v></c></row></sheetData></worksheet>'],
                'the table of texts' => ['xl/sharedStrings.xml', '<!DOCTYPE sst [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;">]>' . "<sst {$main}><si><t>&lol2;</t></si></sst>"],
                'the workbook itself' => ['xl/workbook.xml', '<!DOCTYPE workbook [<!ENTITY a "a">]>' . "<workbook {$main}><sheets><sheet name=\"&a;\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>"],
            ];
            foreach ($hostile as $case => [$part, $xml]) {
                $bytes = self::workbook(['Jahat' => '<row r="1">' . self::cell('A1', 'x') . '</row>'], [$part => $xml]);
                try {
                    $table = self::read($bytes);
                    $this->fail("{$case}: a workbook with a document type declaration is read");
                } catch (GraphException $e) {
                    $this->assertSame(GraphException::UNSUPPORTED, $e->reason, $case);
                    $this->assertStringNotContainsString('ISI-FILE-SERVER', $e->getMessage());
                }
            }
        } finally {
            unlink($secret);
        }
    }

    public function test_a_workbook_is_refused_when_it_is_larger_than_the_limits_say(): void
    {
        // Cells that hold a value, over all sheets (the hidden ones too). A cell that only has a format, or lies beyond the last column, is not one.
        $value = fn (int $n) => str_repeat('<c><v>1</v></c>', $n);
        $formatted = str_repeat('<c s="3"/>', 100);
        $beyond = '<c r="' . Coordinate::stringFromColumnIndex(WorkbookReader::MAX_COLUMNS + 1) . '1"><v>1</v></c>';
        $this->assertSame('read', self::reason(fn () => self::read(self::workbook(['A' => "<row r=\"1\">{$value(3)}{$formatted}</row>", 'B' => "<row>{$value(2)}{$beyond}</row>"]), false, 5)));
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read(self::workbook(['A' => "<row>{$value(3)}</row>", 'B' => "<row>{$value(3)}</row>"]), false, 5)));
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read(self::workbook(['A' => "<row>{$value(6)}</row>"]), false, 5)));
        // A sheet that is hidden in Excel is read like any other, so what is in it counts too.
        $hiddenSecond = ['xl/workbook.xml' => '<workbook ' . self::MAIN . '><sheets><sheet name="A" sheetId="1" r:id="rId1"/><sheet name="B" sheetId="2" state="hidden" r:id="rId2"/></sheets></workbook>'];
        $this->assertSame('read', self::reason(fn () => self::read(self::workbook(['A' => "<row>{$value(3)}</row>", 'B' => "<row>{$value(3)}</row>"], $hiddenSecond), false, 6)));
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read(self::workbook(['A' => "<row>{$value(3)}</row>", 'B' => "<row>{$value(3)}</row>"], $hiddenSecond), false, 5)));

        // Text: what each cell carries counts, a shared text once for every cell that refers to it ...
        $big = str_repeat('x', 1024 * 1024);
        $main = self::MAIN;
        $refer = fn (int $n) => '<row>' . str_repeat('<c t="s"><v>0</v></c>', $n) . '</row>';
        $texts = ['xl/sharedStrings.xml' => "<sst {$main}><si><t>{$big}</t></si></sst>"];
        $this->assertSame('read', self::reason(fn () => self::read(self::workbook(['A' => $refer(20)], $texts))));
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read(self::workbook(['A' => $refer(40)], $texts))));
        // ... and every entry of the table of texts costs something, even an empty one.
        $empties = ['xl/sharedStrings.xml' => "<sst {$main}>" . str_repeat('<si/>', 600_000) . '</sst>'];
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read(self::workbook(['A' => $refer(1)], $empties))));

        // Time: the reading looks at the clock now and then. That is too slow, which is no verdict on the file: not "too large".
        $many = '<row>' . str_repeat('<c><v>1</v></c>', 3000) . '</row>';
        $this->assertSame('read', self::reason(fn () => self::read(self::workbook(['A' => $many]), false, 100_000, 15.0)));
        $this->assertSame(GraphException::TOO_SLOW, self::reason(fn () => self::read(self::workbook(['A' => $many]), false, 100_000, 0.0)));
        // Walking the XML looks at the clock by itself, whatever is done with the cells afterwards.
        $this->assertCount(1, self::sheets(self::workbook(['A' => $many]), 20010, null, 15.0));
        $this->assertSame(GraphException::TOO_SLOW, self::reason(fn () => self::sheets(self::workbook(['A' => $many]), 20010, null, 0.0)));
        // The time is for making text of the cells too, which comes after the XML has been walked: a small workbook has no time for it either.
        $small = self::workbook(['A' => self::cell('A1', 'x') . self::cell('B1', 'y') . self::cell('C1', 'z')]);
        $this->assertSame('read', self::reason(fn () => self::read($small, false, 100, 15.0)));
        $this->assertSame(GraphException::TOO_SLOW, self::reason(fn () => self::read($small, false, 100, 0.0)));

        // What the archive says it inflates to is looked at before anything is read: a sparse file of 260 MB packs into a few hundred KB.
        $sparse = tempnam(sys_get_temp_dir(), 'wbz');
        $handle = fopen($sparse, 'wb');
        ftruncate($handle, 260 * 1024 * 1024);
        fclose($handle);
        $archive = tempnam(sys_get_temp_dir(), 'wbz');
        try {
            $zip = new ZipArchive();
            $zip->open($archive, ZipArchive::OVERWRITE);
            $zip->addFile($sparse, 'xl/media/image1.bin');
            $zip->addFromString('xl/workbook.xml', self::XML . '<workbook ' . $main . '><sheets/></workbook>');
            $zip->close();
            $bomb = (string) file_get_contents($archive);
        } finally {
            unlink($sparse);
            unlink($archive);
        }
        $this->assertLessThan(2 * 1024 * 1024, strlen($bomb));
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read($bomb)));
    }

    public function test_cells_that_only_hold_a_format_are_walked_over_only_so_far(): void
    {
        $formatted = fn (int $n) => '<row r="1">' . self::cell('A1', 'No') . str_repeat('<c s="3"/>', $n) . '</row>';
        $limited = new class extends XlsxReader
        {
            protected const MAX_WALKED = 50;
        };

        // (A cell that holds nothing is not kept, so it does not count against the cells a workbook may have: only against what is walked.)
        $this->assertCount(1, self::sheets(self::workbook(['A' => $formatted(40)]), 20010, $limited));
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::sheets(self::workbook(['A' => $formatted(60)]), 20010, $limited)));
        $this->assertCount(1, self::sheets(self::workbook(['A' => $formatted(60)])), 'The usual limit is a good deal higher.');
    }

    public function test_a_tail_of_empty_rows_is_looked_at_only_so_far(): void
    {
        $emptyRow = fn (int $row) => "<row r=\"{$row}\">" . str_repeat('<c s="3"/>', 10) . '</row>';
        $head = '<row r="1">' . self::cell('A1', 'No') . '</row><row r="2"><c r="A2"><v>2</v></c></row>';
        $tail = fn (int $empty) => implode('', array_map($emptyRow, range(3, 2 + $empty))) . '<row r="' . (3 + $empty) . '"><c r="A' . (3 + $empty) . '"><v>7</v></c></row>';
        $truncated = fn (int $empty, XlsxReader $reader) => self::sheets(self::workbook(['Ekor' => $head . $tail($empty)]), 2, $reader)[0]['truncated'];
        $limited = new class extends XlsxReader
        {
            protected const TAIL_PROBE = 20;
        };

        // Rows past the last that is read are looked at for a value: one soon enough is a sheet that is cut off ...
        $this->assertTrue($truncated(1, $limited));
        // ... one far down, after more empty rows and cells than are looked at, is not found, and the tail is taken for empty.
        $this->assertFalse($truncated(6, $limited));
        $this->assertTrue($truncated(6, new XlsxReader()), 'With the usual limit it is found.');

        // Rows that have no cell at all (only a height) are looked at the same way, one each.
        $bare = fn (int $empty) => implode('', array_map(fn (int $row) => "<row r=\"{$row}\" ht=\"18\"/>", range(3, 2 + $empty)))
            . '<row r="' . (3 + $empty) . '"><c r="A' . (3 + $empty) . '"><v>7</v></c></row>';
        $truncatedBare = fn (int $empty, XlsxReader $reader) => self::sheets(self::workbook(['Ekor' => $head . $bare($empty)]), 2, $reader)[0]['truncated'];
        $this->assertTrue($truncatedBare(5, $limited));
        $this->assertFalse($truncatedBare(25, $limited));
        $this->assertTrue($truncatedBare(25, new XlsxReader()));
    }

    public function test_a_workbook_that_would_not_fit_in_the_memory_is_refused_before_it_runs_out(): void
    {
        // 120,000 cells hold some 30 MB: a script that may use a little more than it uses now has no room for them.
        $rows = implode('', array_map(fn (int $row) => "<row r=\"{$row}\">" . str_repeat('<c><v>1</v></c>', 80) . '</row>', range(1, 1500)));
        $bytes = self::workbook(['Besar' => $rows]);
        $limited = new class extends XlsxReader
        {
            protected function memoryLimit(): int
            {
                return 2 * (memory_get_usage() + 4 * 1024 * 1024); // the reading gives up at half of it: 4 MB above what is in use
            }
        };
        $unlimited = new class extends XlsxReader
        {
            protected function memoryLimit(): int
            {
                return 0;
            }
        };

        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read($bytes, false, XlsxReader::MAX_CELLS, 15.0, $limited)));
        $this->assertSame('read', self::reason(fn () => self::read($bytes, false, XlsxReader::MAX_CELLS, 15.0, $unlimited)), 'Without a limit there is none to keep to.');
        $this->assertSame('read', self::reason(fn () => self::read($bytes)), 'The limit of this machine is far above what it takes.');
    }

    public function test_a_workbook_has_a_number_of_sheets_and_each_costs_something(): void
    {
        $sheets = fn (int $n) => array_fill_keys(array_map(fn (int $at) => "Lembar {$at}", range(1, $n)), self::cell('A1', 'x'));
        $this->assertSame('read', self::reason(fn () => self::read(self::workbook($sheets(500)))));
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read(self::workbook($sheets(501)))));
        $few = new class extends XlsxReader
        {
            protected const MAX_SHEETS = 3;
        };
        $this->assertCount(3, self::sheets(self::workbook($sheets(3)), 20010, $few));
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::sheets(self::workbook($sheets(4)), 20010, $few)));

        // The names are text that is kept: 40 sheets with a name of a million characters are more than a workbook may bring along.
        $named = [];
        foreach (range(1, 40) as $at) {
            $named[str_repeat('n', 1_000_000) . $at] = '';
        }
        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read(self::workbook($named))));
    }

    public function test_a_sheet_that_the_workbook_does_not_tell_where_to_find_is_no_empty_sheet(): void
    {
        $main = self::MAIN;
        $relations = self::RELATIONS;
        $rels = fn (string $relationships) => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
        $oneSheet = "<workbook {$main}><sheets><sheet name=\"Yatim\" sheetId=\"1\" r:id=\"rId9\"/></sheets></workbook>";

        // The relationship the sheet names is not there ...
        $other = self::workbook([], [
            'xl/workbook.xml' => $oneSheet,
            'xl/_rels/workbook.xml.rels' => $rels("<Relationship Id=\"rId1\" Type=\"{$relations}/worksheet\" Target=\"worksheets/sheet1.xml\"/>"),
            'xl/worksheets/sheet1.xml' => "<worksheet {$main}><sheetData><row r=\"1\">" . self::cell('A1', 'x') . '</row></sheetData></worksheet>',
        ]);
        $this->assertSame(GraphException::UNSUPPORTED, self::reason(fn () => self::read($other)));
        // ... or the part of the relationships is not there at all ...
        $none = self::zip(['xl/workbook.xml' => self::XML . $oneSheet]);
        $this->assertSame(GraphException::UNSUPPORTED, self::reason(fn () => self::read($none)));
        // ... but a sheet that is a chart does have its relationship, and a workbook without sheets is a workbook without tables.
        $chart = self::workbook([], [
            'xl/workbook.xml' => $oneSheet,
            'xl/_rels/workbook.xml.rels' => $rels("<Relationship Id=\"rId9\" Type=\"{$relations}/chartsheet\" Target=\"chartsheets/sheet1.xml\"/>"),
        ]);
        $this->assertSame([], self::read($chart));
        $this->assertSame([], self::read(self::zip(['xl/workbook.xml' => self::XML . "<workbook {$main}><sheets/></workbook>"])));
    }

    public function test_the_number_formats_built_into_excel_are_known(): void
    {
        // Style n of the sheet has the built-in format of the same number: 1 is "0", whose code PHP takes for empty.
        $ids = [1 => 1234.5, 2 => 1234.5, 3 => 1234.5, 4 => 1234.5, 9 => 0.125, 10 => 0.125];
        $xfs = '<xf numFmtId="0"/>' . implode('', array_map(fn (int $id) => "<xf numFmtId=\"{$id}\"/>", array_keys($ids)));
        $titles = '';
        $values = '';
        foreach (array_values($ids) as $at => $value) {
            $column = Coordinate::stringFromColumnIndex($at + 1);
            $titles .= self::cell("{$column}1", "F{$at}");
            $values .= sprintf('<c r="%s2" s="%d"><v>%s</v></c>', $column, $at + 1, $value);
        }
        $main = self::MAIN;
        $bytes = self::workbook(['Bawaan' => "<row r=\"1\">{$titles}</row><row r=\"2\">{$values}</row>"], [
            'xl/styles.xml' => "<styleSheet {$main}><cellXfs count=\"" . (count($ids) + 1) . "\">{$xfs}</cellXfs></styleSheet>",
        ]);

        $this->assertSame(['1235', '1234,50', '1.235', '1.234,50', '13%', '12,50%'], self::read($bytes)[0]['rows'][0]);
    }

    public function test_a_format_longer_than_excel_allows_is_not_worked_through_and_the_number_is_shown_as_it_is(): void
    {
        // A format code is 255 characters at most; the library takes longer with every character of it, so a longer one is no format.
        $exactly = '"' . str_repeat('a', 252) . '"0'; // 255 characters
        $longer = '"' . str_repeat('a', 253) . '"0'; // 256
        $crafted = str_repeat('\\a', 50_000);
        $main = self::MAIN;
        $bytes = self::workbook(['Format' => '<row r="1">' . self::cell('A1', 'Satu') . self::cell('B1', 'Dua') . self::cell('C1', 'Tiga') . '</row>'
            . '<row r="2"><c r="A2" s="1"><v>1234.5</v></c><c r="B2" s="2"><v>1234.5</v></c><c r="C2" s="3"><v>1234.5</v></c></row>'], [
                'xl/styles.xml' => "<styleSheet {$main}><numFmts count=\"3\">"
                    . '<numFmt numFmtId="164" formatCode="' . htmlspecialchars($exactly) . '"/><numFmt numFmtId="165" formatCode="' . htmlspecialchars($longer) . '"/><numFmt numFmtId="166" formatCode="' . htmlspecialchars($crafted) . '"/>'
                    . '</numFmts><cellXfs count="4"><xf numFmtId="0"/><xf numFmtId="164"/><xf numFmtId="165"/><xf numFmtId="166"/></cellXfs></styleSheet>',
            ]);
        $this->assertSame(255, strlen($exactly));

        $started = microtime(true);
        $row = self::read($bytes)[0]['rows'][0];

        $this->assertSame(str_repeat('a', 252) . '1235', $row[0]);
        $this->assertSame(['1234,5', '1234,5'], [$row[1], $row[2]]);
        $this->assertLessThan(2.0, microtime(true) - $started);
    }

    public function test_a_number_format_that_belongs_to_a_differential_style_is_not_one_of_the_cells(): void
    {
        // Conditional formatting has styles of its own (dxf), which may carry a numFmt with an id that a cell style uses for a date.
        $main = self::MAIN;
        $bytes = self::workbook(['Tanggal' => '<row r="1">' . self::cell('A1', 'Tanggal') . self::cell('B1', 'x') . self::cell('C1', 'y') . '</row><row r="2"><c r="A2" s="1"><v>45000</v></c></row>'], [
            'xl/styles.xml' => "<styleSheet {$main}><numFmts count=\"1\"><numFmt numFmtId=\"164\" formatCode=\"dd/mm/yyyy\"/></numFmts>"
                . '<cellXfs count="2"><xf numFmtId="0"/><xf numFmtId="164"/></cellXfs>'
                . '<dxfs count="1"><dxf><numFmt numFmtId="164" formatCode="0.0%"/></dxf></dxfs></styleSheet>',
        ]);

        $this->assertSame('15-03-2023', self::read($bytes)[0]['rows'][0][0]);
    }

    public function test_a_part_that_is_damaged_in_the_middle_is_not_shown_cut_off(): void
    {
        // libxml takes a stream that breaks off in the middle of a part for the end of the document: without a check a
        // sheet with a damaged archive would be shown as far as it goes, with nothing to say that the rest is lost.
        $rows = '';
        for ($row = 1; $row <= 4000; $row++) {
            $rows .= "<row r=\"{$row}\">" . self::cell("A{$row}", md5("a{$row}")) . self::cell("B{$row}", md5("b{$row}")) . self::cell("C{$row}", md5("c{$row}")) . '</row>';
        }
        $good = self::workbook(['Besar' => $rows]);
        $this->assertCount(3999, self::read($good)[0]['rows']);

        $file = self::$folder . DIRECTORY_SEPARATOR . 'damage.xlsx';
        file_put_contents($file, $good);
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::RDONLY);
        $entry = $zip->statName('xl/worksheets/sheet1.xml');
        $zip->close();
        $start = self::dataStart($good, 'xl/worksheets/sheet1.xml');

        $refused = 0;
        foreach (range(3, 97, 3) as $percent) {
            $damaged = $good;
            $at = $start + intdiv($entry['comp_size'] * $percent, 100);
            $damaged[$at] = chr(ord($damaged[$at]) ^ 0x5A);

            try {
                $tables = self::read($damaged);
                $this->assertCount(3999, $tables[0]['rows'], "a byte flipped at {$percent}% of the sheet: whatever is shown is the whole of it");
            } catch (GraphException $e) {
                // XML that is no XML (unsupported), or a stream that broke off with nothing wrong in it up to there (the bytes arrived damaged: unavailable).
                $this->assertContains($e->reason, [GraphException::UNSUPPORTED, GraphException::UNAVAILABLE], "at {$percent}%");
                $refused++;
            }
        }
        $this->assertGreaterThan(15, $refused, 'Most damage is told for what it is.');
    }

    public function test_a_stream_that_breaks_off_after_xml_that_is_fine_is_a_file_that_arrived_damaged(): void
    {
        // libxml takes a stream that breaks off for the end of the document. Where nothing is wrong with the XML up to there, it is not XML that
        // is wrong (a verdict on the workbook: "unsupported") but a file that did not arrive whole: "unavailable", so that a copy that was kept
        // is not thrown away for it and the reading is tried again. (The sheet is cut short with a read error, after its hundredth row.)
        $rows = '';
        for ($row = 1; $row <= 300; $row++) {
            $rows .= "<row r=\"{$row}\">" . self::cell("A{$row}", md5("a{$row}")) . self::cell("B{$row}", md5("b{$row}")) . '</row>';
        }
        $bytes = self::workbook(['Besar' => $rows]);

        $file = self::$folder . DIRECTORY_SEPARATOR . 'cut-part.xlsx';
        file_put_contents($file, $bytes);
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::RDONLY);
        $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $after = strpos($xml, '</row>', (int) strpos($xml, '<row r="100"')) + strlen('</row>');
        $this->assertLessThan(strlen($xml), $after);

        try {
            CutsAPartShort::cutShort('xl/worksheets/sheet1.xml', $after);
            $this->assertSame(GraphException::UNAVAILABLE, self::reason(fn () => self::read($bytes)));

            // The same wrapper, cutting nothing: the workbook is read (the cut above is the damage, not the wrapper).
            CutsAPartShort::cutShort(null);
            $tables = self::read($bytes);
            $this->assertCount(299, $tables[0]['rows']);
        } finally {
            CutsAPartShort::restore();
        }
    }

    public function test_the_reading_may_spend_half_of_the_memory_a_script_may_use(): void
    {
        // The other half is for what is made of the cells afterwards. The memory is looked at at every node, and the limit is twice what is in
        // use now less 2 MB: half of it is below what is in use (the reading gives up at once), the whole of it is far above (it would not).
        $bytes = self::workbook(['Kecil' => '<row r="1">' . self::cell('A1', 'x') . '</row>']);
        $half = new class extends XlsxReader
        {
            protected const CHECK_EVERY = 1;

            protected function memoryLimit(): int
            {
                return 2 * (memory_get_usage() - 1024 * 1024);
            }
        };

        $this->assertSame(GraphException::TOO_LARGE, self::reason(fn () => self::read($bytes, false, XlsxReader::MAX_CELLS, 15.0, $half)));
        $this->assertSame('read', self::reason(fn () => self::read($bytes)), 'Without that limit the little workbook is read.');
    }
}
