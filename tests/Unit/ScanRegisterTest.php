<?php

namespace Tests\Unit;

use Modules\SharePointDocs\Services\ScanRegister;
use PHPUnit\Framework\TestCase;

/**
 * The comparison of a sheet whose rows link to scans with the files of a folder. Names, numbers and
 * addresses are made up.
 */
class ScanRegisterTest extends TestCase
{
    private const FOLDER = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/00.%20Dokumen%20&%20Scan/COF%20Scan';

    private const COLUMNS = ['link' => 'Tautan', 'code' => 'COF No', 'note' => 'Note'];

    /** A link as a sheet that builds its addresses by formula writes it: the folder encoded, the name not. */
    private static function link(string $name, string $folder = self::FOLDER): string
    {
        return "{$folder}/{$name}";
    }

    /** A file of the folder as SharePointFolder lists it. */
    private static function file(string $name): array
    {
        return ['name' => $name, 'url' => self::FOLDER . '/' . rawurlencode($name)];
    }

    /**
     * @param array<int, array{0: string, 1: string, 2?: string}> $rows [COF No, link, note] of each row; the rows are lines 2, 3, ... of the sheet
     */
    private static function sheet(array $rows): array
    {
        return [
            'columns' => ['No', 'COF No', 'Tautan', 'Note'],
            'rows' => array_map(fn (array $row, int $at) => [(string) ($at + 1), $row[0], $row[1], $row[2] ?? ''], $rows, array_keys($rows)),
            'lines' => array_map(fn (int $at) => $at + 2, array_keys($rows)),
        ];
    }

    private static function statuses(array $sheet, array $files): array
    {
        return ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ')['statuses'];
    }

    public function test_a_link_is_the_name_of_a_file_and_the_folder_it_is_in(): void
    {
        $this->assertSame(
            ['name' => 'ICTCOF1 - Budi Santoso.pdf', 'names' => ['ICTCOF1 - Budi Santoso.pdf'], 'dir' => '/sites/Team/Shared Documents/00. Dokumen & Scan/COF Scan'],
            ScanRegister::target(self::link('ICTCOF1 - Budi Santoso.pdf'))
        );
        // Encoded the way a browser does it, the same name comes out.
        $this->assertSame('ICTCOF3 - Dewi Putri.pdf', ScanRegister::target('https://contoso.sharepoint.com/sites/Team/ICTCOF3%20-%20Dewi%20Putri.pdf')['name']);
        // Around the cell, and the scheme in capitals.
        $this->assertSame('x.pdf', ScanRegister::target("  HTTPS://contoso.sharepoint.com:443/a/x.pdf \n")['name']);
        // A "/" in a typed name makes a folder of the first part.
        $this->assertSame(
            ['name' => 'La Tantri.pdf', 'names' => ['La Tantri.pdf'], 'dir' => '/sites/Team/COF Scan/ICTCOF4 - Eka'],
            ScanRegister::target('https://contoso.sharepoint.com/sites/Team/COF Scan/ICTCOF4 - Eka/La Tantri.pdf')
        );
        // A base that ends in a slash does not make another folder of the folder.
        $this->assertSame('/sites/Team/COF Scan', ScanRegister::target('https://contoso.sharepoint.com/sites/Team/COF Scan//ICTCOF1 - A.pdf')['dir']);
        // The name goes after the last slash, and the host is no part of the folder.
        $this->assertSame(['name' => 'b.pdf', 'names' => ['b.pdf'], 'dir' => ''], ScanRegister::target('https://contoso.sharepoint.com/b.pdf'));

        foreach (['', 'ICTCOF1', 'http://contoso.sharepoint.com/a/b.pdf', '//contoso.sharepoint.com/a/b.pdf', 'file:///c:/b.pdf', 'https://contoso.sharepoint.com',
            'https://contoso.sharepoint.com/', 'https://contoso.sharepoint.com/a/', 'https://contoso.sharepoint.com/a/%FF.pdf', 'lihat https://contoso.sharepoint.com/a/b.pdf',
            'https:///a/b.pdf', "https://contoso.sharepoint.com/a/b\n.pdf", "https://contoso.sharepoint.com/a/b\r.pdf", 'https://contoso sharepoint.com/a/b.pdf',
            "https://contoso\tsharepoint.com/a/b.pdf"] as $text) {
            $this->assertNull(ScanRegister::target($text), $text);
        }
    }

    public function test_the_path_of_a_link_from_the_address_bar_of_a_library_or_from_copy_link_is_read_out_of_it(): void
    {
        $host = 'https://contoso.sharepoint.com';
        $dir = '/sites/Team/Shared Documents/00. Dokumen & Scan/COF Scan';
        $id = rawurlencode("{$dir}/ICTCOF1 - Budi.pdf");
        $parent = rawurlencode($dir);
        $viewer = "{$host}/sites/Team/Shared%20Documents/Forms/AllItems.aspx";
        $expected = ['name' => 'ICTCOF1 - Budi.pdf', 'names' => ['ICTCOF1 - Budi.pdf'], 'dir' => $dir];

        // The library as it is in the address bar: the file is the "id", whatever else the address says (a "/" in the rest is no part of the name).
        foreach ([
            "{$viewer}?id={$id}",
            "{$viewer}?id={$id}&parent={$parent}",
            "{$viewer}?id={$id}&viewid=1a2b3c4d-0000",
            "{$viewer}?e=5%3Aabc&id={$id}&parent={$parent}",
            "{$viewer}?id={$id}&x=a/b",
            "{$host}/sites/Team/Shared%20Documents/Forms/ALLITEMS.ASPX?ID={$id}",
            "{$viewer}?id=" . str_replace('%2F', '/', $id),
        ] as $link) {
            $this->assertSame($expected, ScanRegister::target($link), $link);
        }
        // The same folder as a link built by formula is in: the id is what the file is called there.
        $this->assertSame($expected['dir'], ScanRegister::target(self::link('ICTCOF1 - Budi.pdf'))['dir']);

        // "Copy link": a prefix in front of the path, a query after the name (that is the second name).
        $copied = ScanRegister::target("{$host}/:b:/r/sites/Team/Shared%20Documents/00.%20Dokumen%20&%20Scan/COF%20Scan/ICTCOF1%20-%20Budi.pdf?csf=1&web=1&e=AbC123");
        $this->assertSame($dir, $copied['dir']);
        $this->assertSame(['ICTCOF1 - Budi.pdf?csf=1&web=1&e=AbC123', 'ICTCOF1 - Budi.pdf'], $copied['names']);
        $this->assertSame('/sites/Team/Shared Documents/x', ScanRegister::target("{$host}/:w:/r/sites/Team/Shared%20Documents/x/a.docx")['dir'], 'any kind of file');
        $this->assertSame('/sites/Team/Shared Documents/x', ScanRegister::target("{$host}/:X:/R/sites/Team/Shared%20Documents/x/a.xlsx")['dir'], 'letter case');

        // What is not a path in them is left alone: a link by a token, an address that is no library, an "id" that is no path, a prefix in the middle.
        $this->assertSame('/:b:/s/sites/Team', ScanRegister::target("{$host}/:b:/s/sites/Team/EaBcD?e=x")['dir']);
        $this->assertSame(['name' => 'Home.aspx?id=abc', 'names' => ['Home.aspx?id=abc', 'Home.aspx'], 'dir' => '/sites/Team/SitePages'], ScanRegister::target("{$host}/sites/Team/SitePages/Home.aspx?id=abc"));
        $this->assertSame('/sites/Team/x/:b:/r/y', ScanRegister::target("{$host}/sites/Team/x/:b:/r/y/a.pdf")['dir']);
        // The prefix of a file in the root of the site is taken off as well.
        $this->assertSame(['name' => 'a.pdf', 'names' => ['a.pdf'], 'dir' => ''], ScanRegister::target("{$host}/:b:/r/a.pdf"));
        // It is "/:b:/r" and nothing longer: a first segment that only begins that way is a folder like any other.
        $this->assertSame('/:b:/random', ScanRegister::target("{$host}/:b:/random/a.pdf")['dir']);
        // The page of a library is an .aspx page and its query starts at the first "?" of the address: an "id" further on is a part of a name...
        $this->assertSame('/a.pdf?x=1/b.aspx?id=/sites/z', ScanRegister::target("{$host}/a.pdf?x=1/b.aspx?id=/sites/z/ICTCOF9.pdf")['dir']);
        // ...and a page of any other kind has no file in its "id".
        $this->assertSame('/sites/Team/page.php?id=/sites/x', ScanRegister::target("{$host}/sites/Team/page.php?id=%2Fsites%2Fx%2FICTCOF9.pdf")['dir']);
    }

    public function test_the_links_of_the_address_bar_find_the_file_like_the_plain_ones_do(): void
    {
        $files = [self::file('ICTCOF1 - Budi.pdf'), self::file('ICTCOF2 - Andi.pdf')];
        $viewer = 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Forms/AllItems.aspx';
        $in = fn (string $name, string $dir = '/sites/Team/Shared Documents/00. Dokumen & Scan/COF Scan') => rawurlencode("{$dir}/{$name}");
        $sheet = self::sheet([
            ['ICT/COF/1', "{$viewer}?id=" . $in('ICTCOF1 - Budi.pdf') . '&parent=' . rawurlencode('/sites/Team/Shared Documents/00. Dokumen & Scan/COF Scan')],
            ['ICT/COF/2', "{$viewer}?id=" . $in('ICTCOF2 - Andi.pdf') . '&viewid=1a2b3c4d'],
            ['ICT/COF/3', "{$viewer}?id=" . $in('ICTCOF3 - Citra.pdf')],
            ['ICT/COF/4', 'https://contoso.sharepoint.com/:b:/r/sites/Team/Shared%20Documents/00.%20Dokumen%20&%20Scan/COF%20Scan/Arsip/ICTCOF1%20-%20Budi.pdf?csf=1&e=x'],
        ]);

        $this->assertSame([ScanRegister::OK, ScanRegister::OK, ScanRegister::MISSING, ScanRegister::BELOW], self::statuses($sheet, $files));
        // A link into a folder below does not name a file of the folder, so the two files are named by the first two rows only.
        $this->assertSame([ScanRegister::LINKED, ScanRegister::LINKED], array_column(ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - '), 'status'));
    }

    public function test_what_is_typed_for_a_number_and_has_no_letter_or_digit_is_no_number(): void
    {
        $files = [self::file('ICTCOF7 - Gita Lestari.pdf')];
        $sheet = self::sheet([
            ['-', self::link('ICTCOF7 - Gita.pdf')],       // a dash is no number: the link's is
            [' / ', self::link('ICTCOF7 - Gita.pdf')],
            ['-', ''],                                      // and with no link there is none at all
            ['N/A', self::link('ICTCOF7 - Gita.pdf')],     // letters make a number, though none that a file has: it is not set aside for the link's
            ['123', self::link('ICTCOF7 - Gita.pdf')],     // so do digits
        ]);

        $this->assertSame([ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::MISSING, ScanRegister::MISSING, ScanRegister::MISSING], self::statuses($sheet, $files));
        $byFile = ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - ');
        $this->assertSame([[2, 3]], array_column($byFile, 'lines'));
    }

    public function test_the_longest_number_a_link_can_ask_for_is_the_longest_name_a_file_can_have(): void
    {
        $this->assertSame(400, ScanRegister::CODE_MOST);
    }

    public function test_the_part_of_a_link_that_is_no_name_is_also_tried_without(): void
    {
        // The names that were typed in are not encoded: a "?", a "#", a "%" and a "+" are part of the name, so that is the first name...
        $typed = ScanRegister::target(self::link('ICTCOF2 - Rak #3 (A+B) 50%?.pdf'));
        $this->assertSame('ICTCOF2 - Rak #3 (A+B) 50%?.pdf', $typed['name']);
        // ...but a link copied from the viewer of SharePoint ends in a query or a fragment, which the name does not have: that is the second.
        $this->assertSame(['ICTCOF6 - F.pdf?web=1', 'ICTCOF6 - F.pdf'], ScanRegister::target(self::link('ICTCOF6 - F.pdf?web=1'))['names']);
        $this->assertSame(['ICTCOF7 - G.pdf#page=2', 'ICTCOF7 - G.pdf'], ScanRegister::target(self::link('ICTCOF7 - G.pdf#page=2'))['names']);
        $this->assertSame(['ICTCOF2 - Rak #3 (A+B) 50%?.pdf', 'ICTCOF2 - Rak '], $typed['names']);
        // Nothing is left of a name that starts with one.
        $this->assertSame(['#hanya'], ScanRegister::target(self::link('#hanya'))['names']);

        $files = [self::file('ICTCOF6 - F.pdf')];
        $sheet = self::sheet([['ICT/COF/6', self::link('ICTCOF6 - F.pdf?web=1')]]);
        $this->assertSame([ScanRegister::OK], self::statuses($sheet, $files));
        $this->assertSame([ScanRegister::LINKED], array_column(ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - '), 'status'));
    }

    public function test_names_and_numbers_are_compared_the_way_the_register_means_them(): void
    {
        // A name differs when a space differs; letter case is no difference (SharePoint does not tell them apart).
        $this->assertSame(ScanRegister::nameKey('ICTCOF1 - BUDI.PDF'), ScanRegister::nameKey('ictcof1 - Budi.pdf'));
        $this->assertNotSame(ScanRegister::nameKey('ICTCOF1 - Budi  Santoso.pdf'), ScanRegister::nameKey('ICTCOF1 - Budi Santoso.pdf'));
        $this->assertNotSame(ScanRegister::nameKey(' ICTCOF1.pdf'), ScanRegister::nameKey('ICTCOF1.pdf'));
        $this->assertSame(ScanRegister::nameKey('ÉKA.pdf'), ScanRegister::nameKey('éka.PDF'));

        $this->assertSame('ictcof123', ScanRegister::codeKey('ICT/COF/123'));
        $this->assertSame('ictcof123', ScanRegister::codeKey(' ICTCOF123 '));
        $this->assertSame('ictcof123', ScanRegister::codeKey('ICT-COF-123'), 'a dash is no part of a number');
        $this->assertSame('ictcof123', ScanRegister::codeKey('ict.cof.123.'));
        $this->assertSame('srf123', ScanRegister::codeKey('SRF/123'));
        $this->assertNotSame(ScanRegister::codeKey('ICTSRF123'), ScanRegister::codeKey('ICTCOF123'), 'the letters count, not only the digits');
        $this->assertNotSame(ScanRegister::codeKey('ICTCOF1'), ScanRegister::codeKey('ICTCOF001'));
        $this->assertSame('', ScanRegister::codeKey(' / '));

        $this->assertSame('ICTCOF123', ScanRegister::codeOfFile('ICTCOF123 - Nama Penerima.pdf', ' - '));
        $this->assertSame('ICTCOF123', ScanRegister::codeOfFile('ICTCOF123  - Nama.pdf', ' - '), 'blanks left over in front of the separator are no part of the number');
        $this->assertSame('Zoë Ünal', ScanRegister::codeOfFile('Zoë Ünal - Nama.pdf', ' - '), 'characters of more than one byte in front of the separator');
        $this->assertSame('Zoë Ünal - Nama', ScanRegister::codeOfFile('Zoë Ünal - Nama.pdf', ''), 'and with no separator at all');
        $this->assertSame('ICTCOF123', ScanRegister::codeOfFile('ICTCOF123 - Nama - Lain.PDF', ' - '), 'the first separator counts');
        $this->assertSame('ICTCOF123', ScanRegister::codeOfFile('ICTCOF123.pdf', ' - '), 'no separator: the whole name without its extension');
        $this->assertSame('ICTCOF123', ScanRegister::codeOfFile('ICTCOF123.docx', ' - '), 'an extension of more than three letters is one too');
        $this->assertSame('ICTCOF123', ScanRegister::codeOfFile('ICTCOF123.abcdefghij', ' - '), 'up to ten');
        $this->assertSame('ICTCOF123.abcdefghijk', ScanRegister::codeOfFile('ICTCOF123.abcdefghijk', ' - '), 'longer than that it is part of the name');
        $this->assertSame('A.B', ScanRegister::codeOfFile('A.B - C.pdf', ' - '), 'only the last dot is an extension');
        $this->assertSame('catatan', ScanRegister::codeOfFile('catatan', ' - '));
        $this->assertSame('- Nama', ScanRegister::codeOfFile('- Nama.pdf', '- '), 'a name that starts with the separator has no number in front of it');
        $this->assertSame('ICTCOF1 - Nama', ScanRegister::codeOfFile('ICTCOF1 - Nama.pdf', ''));
    }

    public function test_the_folder_is_told_by_where_most_of_its_files_are(): void
    {
        $this->assertSame('/sites/team/shared documents/00. dokumen & scan/cof scan', ScanRegister::folderOf([self::file('a.pdf'), self::file('b.pdf')]));
        // One file with another address does not move it, whichever file is listed first; files without an address say nothing.
        $other = ['name' => 'b.pdf', 'url' => 'https://contoso.sharepoint.com/elsewhere/b.pdf'];
        $this->assertSame(
            '/sites/team/shared documents/00. dokumen & scan/cof scan',
            ScanRegister::folderOf([$other, self::file('a.pdf'), self::file('c.pdf'), ['name' => 'd.pdf']])
        );
        $this->assertSame('/elsewhere', ScanRegister::folderOf([self::file('a.pdf'), $other, $other, $other]), 'the folder most of the files are in');
        $this->assertNull(ScanRegister::folderOf([]));
        $this->assertNull(ScanRegister::folderOf([['name' => 'a.pdf', 'url' => null], ['name' => 'b.pdf']]));
    }

    public function test_a_column_is_found_by_its_title(): void
    {
        $sheet = ['columns' => ['Kolom A', 'COF No', 'Column4', ' note '], 'rows' => [], 'lines' => []];

        $this->assertSame(1, ScanRegister::column($sheet, 'COF No'));
        $this->assertSame(2, ScanRegister::column($sheet, 'Column4'));
        $this->assertSame(3, ScanRegister::column($sheet, 'Note'), 'letter case and spaces around the title do not matter');
        $this->assertSame(3, ScanRegister::column($sheet, ' NOTE '));
        $this->assertSame(1, ScanRegister::column($sheet, 'cof no'));
        $this->assertNull(ScanRegister::column($sheet, 'Tautan'));
        $this->assertNull(ScanRegister::column($sheet, ''));
        $this->assertNull(ScanRegister::column($sheet, null));
        // No title is no column, even when a column has none.
        $this->assertNull(ScanRegister::column(['columns' => ['', 'COF No'], 'rows' => [], 'lines' => []], ''));
        $this->assertNull(ScanRegister::column(['columns' => ['', ' '], 'rows' => [], 'lines' => []], null));

        // The title as it is written is preferred to one that only looks like it.
        $twin = ['columns' => ['note', 'Note', 'Note '], 'rows' => [], 'lines' => []];
        $this->assertSame(1, ScanRegister::column($twin, 'Note'));
        $this->assertSame(0, ScanRegister::column($twin, 'NOTE'), 'none is exact: the first that looks like it');
        // A column whose title is a number is found by that number as text.
        $this->assertSame(1, ScanRegister::column(['columns' => ['No', '2025'], 'rows' => [], 'lines' => []], '2025'));
        // Two titles that are equal as numbers are not one title ("1e3" is 1000 to a loose comparison).
        $this->assertNull(ScanRegister::column(['columns' => ['No', '1000'], 'rows' => [], 'lines' => []], '1e3'));
        $this->assertSame(1, ScanRegister::column(['columns' => ['No', '1000'], 'rows' => [], 'lines' => []], '1000'));
    }

    public function test_every_row_is_told_by_its_link_against_the_files_of_the_folder(): void
    {
        $files = [
            self::file('ICTCOF1 - Budi Santoso.pdf'),
            self::file('ICTCOF2 - Andi  Wijaya.pdf'),   // two spaces
            self::file('ICTCOF3 - Citra Dewi.pdf'),
            self::file('ICTCOF5 - Dewi Putri.PDF'),
            self::file('ICTCOF6 - Fajar La Tantri.pdf'),
            self::file('ICTCOF7 - Gita.pdf'),
            self::file('ICTCOF10 - Hadi  Putra.pdf'),   // two spaces
        ];
        $sheet = self::sheet([
            ['ICT/COF/1', self::link('ICTCOF1 - Budi Santoso.pdf')],             // line 2: the file
            ['ICT/COF/2', self::link('ICTCOF2 - Andi  Wijaya.pdf')],             // line 3: two spaces, as the file has: the link finds it
            ['ICT/COF/3', self::link('ictcof3 - CITRA DEWI.pdf')],               // line 4: letter case only
            ['ICT/COF/4', self::link('ICTCOF4 - Eka Lestari.pdf')],              // line 5: no file with that name or number
            ['ICT/COF/5', self::link('ICTCOF5 - Dewi Putri.pdf')],               // line 6: the extension in capitals
            ['ICT/COF/6', self::link('ICTCOF6 - Fajar/La Tantri.pdf')],          // line 7: a "/" in the name, so a folder; the file is there under another name
            ['ICT/COF/7', ''],                                                   // line 8: no link, but the file is there
            ['ICT/COF/8', 'bukan tautan'],                                       // line 9: no address, no file
            ['ICT/COF/9', self::link('ICTCOF9 - Gita.pdf', 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/00.%20Dokumen%20&%20Scan/Lama')], // line 10: another folder, no such file
            ['ICT/COF/10', self::link('ICTCOF10 - Hadi Putra.pdf')],             // line 11: one space where the file has two: the link does not find it
        ]);

        $result = ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ');

        $this->assertSame(
            [ScanRegister::OK, ScanRegister::OK, ScanRegister::OK, ScanRegister::MISSING, ScanRegister::OK, ScanRegister::BELOW, ScanRegister::DIFFERS, ScanRegister::MISSING, ScanRegister::MISSING, ScanRegister::DIFFERS],
            $result['statuses']
        );
        $this->assertSame(['total' => 10, 'ok' => 4, 'missing' => 3, 'differs' => 2, 'below' => 1], $result['counts']);
        // Where each row goes in the folder: the file its link names, else the one that has its number; nowhere when there is none.
        $this->assertSame(['ICTCOF1', 'ICTCOF2', 'ICTCOF3', '', 'ICTCOF5', 'ICTCOF6', 'ICTCOF7', '', '', 'ICTCOF10'], $result['codes']);
    }

    public function test_when_two_files_have_the_same_name_or_number_the_first_one_is_the_one_a_row_goes_to(): void
    {
        // Two names that differ in letter case only are one name; the number of the first of them is the one a row goes to.
        $files = [self::file('ICTCOF1 - A.pdf'), self::file('ictcof1 - a.PDF')];
        $sheet = self::sheet([['ICT/COF/1', self::link('ICTCOF1 - A.pdf')]]);
        $this->assertSame(['ICTCOF1'], ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ')['codes']);
        $this->assertSame(['ictcof1'], ScanRegister::checkRows($sheet, array_reverse($files), self::COLUMNS, ' - ')['codes']);

        // Two files that are written differently but have one number: the number of the first of them.
        $files = [self::file('ICT-COF-2 - X.pdf'), self::file('ICTCOF2 - Y.pdf')];
        $sheet = self::sheet([['ICT/COF/2', '']]);
        $this->assertSame(['ICT-COF-2'], ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ')['codes']);
        $this->assertSame(['ICTCOF2'], ScanRegister::checkRows($sheet, array_reverse($files), self::COLUMNS, ' - ')['codes']);
    }

    public function test_a_row_goes_to_the_file_its_link_names_even_when_its_own_number_is_another(): void
    {
        $files = [self::file('ICTCOF4 - Citra.pdf'), self::file('ICTCOF3 - Dewi.pdf')];
        // The typed number is a slip (3 for 4): the link names the file that is there, and the way goes to that file.
        $sheet = self::sheet([['ICT/COF/3', self::link('ICTCOF4 - Citra.pdf')]]);
        $result = ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ');
        $this->assertSame([ScanRegister::OK], $result['statuses']);
        $this->assertSame(['ICTCOF4'], $result['codes']);
    }

    public function test_a_row_without_a_usable_link_is_told_by_its_number_like_the_file_is(): void
    {
        $files = [self::file('ICTCOF7 - Gita.pdf'), self::file('ICTCOF8 - Hadi.pdf'), self::file('ICTCOF9 - Ina.pdf'), self::file('ICTCOF11 - Joko.pdf')];
        $sheet = self::sheet([
            ['ICT/COF/7', ''],                                                           // nothing pasted yet
            ['ICT/COF/8', 'https://contoso.sharepoint.com/a/%FF.pdf'],                    // not a name
            ['ICT/COF/9', 'lihat scan'],                                                 // not an address
            ['ICT/COF/11', self::FOLDER . '/'],                                          // an address that names no file
            ['ICT/COF/12', ''],                                                          // no file either
        ]);

        $this->assertSame(
            [ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::MISSING],
            self::statuses($sheet, $files)
        );
        // And the files say the same of these rows, so that the two pages tell one story.
        $byFile = ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - ');
        $this->assertSame([ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::DIFFERS], array_column($byFile, 'status'));
        $this->assertSame([[2], [3], [4], [5]], array_column($byFile, 'lines'));
    }

    public function test_the_number_of_a_row_is_the_typed_one_or_else_the_one_of_its_link(): void
    {
        $files = [self::file('ICTCOF7 - Gita Lestari.pdf')];
        $sheet = self::sheet([
            ['ICT/COF/7', self::link('ICTCOF7 - Gita.pdf')],     // typed number
            ['', self::link('ICTCOF7 - Gita L.pdf')],            // none typed: the link's
            ['  ', self::link('ICTCOF7 - Gita M.pdf')],          // blanks only: none typed
            ['', self::link('Tanpa nomor.pdf')],                 // neither
            ['ICT-COF-7', self::link('Lain.pdf')],               // typed in another way: the same number
        ]);

        $result = ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ');
        $this->assertSame([ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::MISSING, ScanRegister::DIFFERS], $result['statuses']);
        $this->assertSame(['ICTCOF7', 'ICTCOF7', 'ICTCOF7', '', 'ICTCOF7'], $result['codes']);

        // A sheet that has no column for the number goes by the links alone.
        $result = ScanRegister::checkRows($sheet, $files, ['link' => 'Tautan'], ' - ');
        $this->assertSame([ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::MISSING, ScanRegister::MISSING], $result['statuses']);
        $result = ScanRegister::checkRows($sheet, $files, ['link' => 'Tautan', 'code' => 'Tidak Ada'], ' - ');
        $this->assertSame([ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::MISSING, ScanRegister::MISSING], $result['statuses']);
    }

    public function test_a_folder_without_addresses_has_no_folder_below_it(): void
    {
        // Without the address of any file there is no telling a folder below from the folder: the name decides.
        $files = [['name' => 'ICTCOF1 - Budi.pdf'], ['name' => 'ICTCOF6 - Fajar.pdf']];
        $sheet = self::sheet([
            ['ICT/COF/1', self::link('ICTCOF1 - Budi.pdf')],
            ['ICT/COF/6', self::link('ICTCOF6 - Fajar/La Tantri.pdf')],
        ]);

        $this->assertSame([ScanRegister::OK, ScanRegister::DIFFERS], self::statuses($sheet, $files));
        $this->assertSame([ScanRegister::LINKED, ScanRegister::DIFFERS], array_column(ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - '), 'status'));
    }

    public function test_a_folder_that_only_looks_like_the_folder_is_not_below_it(): void
    {
        $files = [self::file('ICTCOF1 - Budi.pdf')];
        $sheet = self::sheet([
            ['ICT/COF/2', self::link('ICTCOF2 - Eka.pdf', self::FOLDER . '2')],     // "COF Scan2" is no folder of "COF Scan"
            ['ICT/COF/3', self::link('ICTCOF3 - Dewi.pdf', self::FOLDER . '/2025')], // a folder of it
            ['ICT/COF/4', self::link('ICTCOF4 - Eka.pdf', strtoupper(self::FOLDER) . '/Arsip')], // a folder of it, in capitals
            ['ICT/COF/5', self::link('ICTCOF5 - Fajar.pdf', self::FOLDER . '/')],   // the folder itself, with a slash too many
            ['ICT/COF/6', self::link('ICTCOF6 - Gita.pdf', dirname(self::FOLDER))], // the folder above
        ]);

        $this->assertSame(
            [ScanRegister::MISSING, ScanRegister::BELOW, ScanRegister::BELOW, ScanRegister::MISSING, ScanRegister::MISSING],
            self::statuses($sheet, $files)
        );
    }

    public function test_a_link_into_a_folder_below_is_below_whatever_the_file_is_called(): void
    {
        $files = [self::file('ICTCOF6 - Fajar La Tantri.pdf'), self::file('La Tantri.pdf'), self::file('ICTCOF1 - Budi.pdf')];
        $sheet = self::sheet([
            ['ICT/COF/6', self::link('ICTCOF6 - Fajar/La Tantri.pdf')],   // the folder it makes up is below; a file of that name is in the folder
            ['ICT/COF/1', self::link('ICTCOF1 - Budi.pdf', self::FOLDER . '/Arsip')],  // the same name as a file of the folder, in a folder below
        ]);

        $result = ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ');
        $this->assertSame([ScanRegister::BELOW, ScanRegister::BELOW], $result['statuses']);
        // The way goes to the file that is there: the one the link names, else the one with the row's number.
        $this->assertSame(['La Tantri', 'ICTCOF1'], $result['codes']);

        // The files agree: a link into a folder below does not name a file of this one, whatever its last part is.
        $byFile = ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - ');
        $this->assertSame([ScanRegister::DIFFERS, ScanRegister::UNLINKED, ScanRegister::DIFFERS], array_column($byFile, 'status'));
        $this->assertSame([[2], [], [3]], array_column($byFile, 'lines'));
    }

    public function test_a_row_below_with_no_file_goes_nowhere(): void
    {
        $files = [self::file('ICTCOF1 - Budi.pdf')];
        $sheet = self::sheet([['ICT/COF/8', self::link('ICTCOF8 - Hadi/Putra.pdf')]]);

        $result = ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ');
        $this->assertSame([ScanRegister::BELOW], $result['statuses']);
        $this->assertSame([''], $result['codes']);
    }

    public function test_a_base_that_ends_in_a_slash_is_still_the_folder(): void
    {
        $files = [self::file('ICTCOF1 - A.pdf')];
        $sheet = self::sheet([['ICT/COF/1', self::FOLDER . '//ICTCOF1 - A.pdf']]);

        $this->assertSame([ScanRegister::OK], self::statuses($sheet, $files));
        $this->assertSame([ScanRegister::LINKED], array_column(ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - '), 'status'));
    }

    public function test_a_sheet_without_the_column_of_links_cannot_be_checked(): void
    {
        $sheet = ['columns' => ['No', 'COF No'], 'rows' => [['1', 'ICT/COF/1']], 'lines' => [2]];

        $this->assertNull(ScanRegister::checkRows($sheet, [self::file('a.pdf')], self::COLUMNS, ' - '));
        $this->assertNull(ScanRegister::checkFiles($sheet, [self::file('a.pdf')], self::COLUMNS, ' - '));
        $this->assertNull(ScanRegister::checkRows($sheet, [], ['code' => 'COF No'], ' - '));
    }

    public function test_an_empty_folder_and_an_empty_sheet_are_checked_too(): void
    {
        $sheet = self::sheet([['ICT/COF/1', self::link('ICTCOF1 - Budi.pdf')]]);
        $this->assertSame([ScanRegister::MISSING], self::statuses($sheet, []));

        $none = self::sheet([]);
        $result = ScanRegister::checkRows($none, [self::file('a.pdf')], self::COLUMNS, ' - ');
        $this->assertSame(['total' => 0, 'ok' => 0, 'missing' => 0, 'differs' => 0, 'below' => 0], $result['counts']);
        $this->assertSame([2 => ['status' => ScanRegister::UNLINKED, 'lines' => [], 'total' => 0, 'note' => null]], ScanRegister::checkFiles($none, [2 => self::file('a.pdf')], self::COLUMNS, ' - '));
        $this->assertSame([], ScanRegister::checkFiles($sheet, [], self::COLUMNS, ' - '));
    }

    public function test_every_file_is_told_by_the_rows_that_name_it_or_have_its_number(): void
    {
        $files = [
            3 => self::file('ICTCOF1 - Budi Santoso.pdf'),
            5 => self::file('ICTCOF2 - Andi  Wijaya.pdf'),
            8 => self::file('ICTCOF9 - Tidak Ada Barisnya.pdf'),
            9 => self::file('ICTCOF4 - Eka Lestari.pdf'),
        ];
        $sheet = self::sheet([
            ['ICT/COF/1', self::link('ICTCOF1 - Budi Santoso.pdf'), 'Menyusul'],            // line 2
            ['ICT/COF/2', self::link('ICTCOF2 - Andi Wijaya.pdf'), 'Nama diganti'],         // line 3: one space where the file has two
            ['ICT/COF/3', self::link('ICTCOF3 - Citra.pdf'), ''],                           // line 4
            ['ICT/COF/4', self::link('ICTCOF4 - Eka Lestari.pdf'), '  '],                   // line 5: the note is only spaces
            ['ICT/COF/4', self::link('ictcof4 - eka lestari.PDF'), 'Rangkap'],              // line 6: a second row for the same file
            ['ICT/COF/4', self::link('ICTCOF4 - Eka Lestari.pdf'), 'Ketiga'],               // line 7: a third, with a note of its own
        ]);

        $result = ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - ');

        $this->assertSame([3, 5, 8, 9], array_keys($result), 'the keys of the files are kept');
        $this->assertSame(['status' => ScanRegister::LINKED, 'lines' => [2], 'total' => 1, 'note' => 'Menyusul'], $result[3]);
        $this->assertSame(['status' => ScanRegister::DIFFERS, 'lines' => [3], 'total' => 1, 'note' => 'Nama diganti'], $result[5]);
        $this->assertSame(['status' => ScanRegister::UNLINKED, 'lines' => [], 'total' => 0, 'note' => null], $result[8]);
        // Three rows name it: their lines are told, the note is that of the first row that says something.
        $this->assertSame(['status' => ScanRegister::LINKED, 'lines' => [5, 6, 7], 'total' => 3, 'note' => 'Rangkap'], $result[9]);
    }

    public function test_a_name_wins_over_a_number_and_the_rows_with_the_number_are_the_second_choice(): void
    {
        // A second file with the number a row already has a file for: its row is told, and so is that the row names the other one.
        $files = [self::file('ICTCOF100 - Budi.pdf'), self::file('ICTCOF100 - Budi (2).pdf')];
        $sheet = self::sheet([['ICT/COF/100', self::link('ICTCOF100 - Budi.pdf')]]);

        $byFile = ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - ');
        $this->assertSame([ScanRegister::LINKED, ScanRegister::DIFFERS], array_column($byFile, 'status'));
        $this->assertSame([[2], [2]], array_column($byFile, 'lines'));
        $this->assertSame([ScanRegister::OK], self::statuses($sheet, $files));
        $this->assertSame(['ICTCOF100'], ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ')['codes']);
    }

    public function test_a_file_tells_of_the_first_rows_only_and_counts_the_rest(): void
    {
        $files = [self::file('ICTCOF1 - Budi.pdf')];
        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = ['ICT/COF/1', self::link('ICTCOF1 - Budi.pdf')];
        }

        $one = ScanRegister::checkFiles(self::sheet($rows), $files, self::COLUMNS, ' - ')[0];

        $this->assertSame(range(2, 21), $one['lines']);
        $this->assertSame(20, ScanRegister::LINES_MOST);
        $this->assertSame(30, $one['total']);
    }

    public function test_a_long_note_is_cut_for_the_page_and_a_sheet_without_notes_has_none(): void
    {
        $files = [self::file('ICTCOF1 - Budi.pdf')];
        $note = fn (string $text) => ScanRegister::checkFiles(self::sheet([['ICT/COF/1', self::link('ICTCOF1 - Budi.pdf'), $text]]), $files, self::COLUMNS, ' - ')[0]['note'];

        // Cut at 500 characters (not bytes), and the page is told that something is missing.
        $cut = $note(str_repeat('é', 800));
        $this->assertSame(str_repeat('é', 500) . '…', $cut);
        $this->assertSame(501, mb_strlen($cut));
        // A note of exactly that length, and one a character shorter, are as they were typed.
        $this->assertSame(str_repeat('é', 500), $note(str_repeat('é', 500)));
        $this->assertSame(str_repeat('é', 499), $note(str_repeat('é', 499)));
        $this->assertSame(str_repeat('é', 500) . '…', $note(str_repeat('é', 501)));
        $this->assertSame('catatan', $note("  catatan \n"), 'blanks around a note are not part of it');

        $sheet = self::sheet([['ICT/COF/1', self::link('ICTCOF1 - Budi.pdf'), 'ada']]);
        $this->assertNull(ScanRegister::checkFiles($sheet, $files, ['link' => 'Tautan', 'code' => 'COF No'], ' - ')[0]['note'], 'no column of notes is set');
        $this->assertNull(ScanRegister::checkFiles($sheet, $files, ['link' => 'Tautan', 'note' => 'Catatan'], ' - ')[0]['note'], 'a column of notes that the sheet does not have');
        $this->assertSame('ada', ScanRegister::checkFiles($sheet, $files, ['link' => 'Tautan', 'note' => 'note'], ' - ')[0]['note']);
    }

    public function test_the_two_directions_agree_on_the_pairs(): void
    {
        // Whatever the rows say about a file, the file says about the row: OK <=> LINKED; DIFFERS, BELOW and a row without a link <=> DIFFERS.
        $files = [
            self::file('ICTCOF1 - Budi.pdf'),
            self::file('ICTCOF2 - Andi  Wijaya.pdf'),
            self::file('ICTCOF3 - Citra.pdf'),
            self::file('ICTCOF6 - Fajar La Tantri.pdf'),
            self::file('ICTCOF7 - Gita.pdf'),
            self::file('ICTCOF9 - Tanpa Baris.pdf'),
        ];
        $sheet = self::sheet([
            ['ICT/COF/1', self::link('ICTCOF1 - Budi.pdf')],                    // OK
            ['ICT/COF/2', self::link('ICTCOF2 - Andi Wijaya.pdf')],             // another name
            ['ICT/COF/4', self::link('ICTCOF4 - Eka.pdf')],                     // no file
            ['ICT/COF/6', self::link('ICTCOF6 - Fajar/La Tantri.pdf')],         // a folder below
            ['ICT/COF/7', ''],                                                  // no link
        ]);

        $rows = self::statuses($sheet, $files);
        $byFile = ScanRegister::checkFiles($sheet, $files, self::COLUMNS, ' - ');

        $this->assertSame([ScanRegister::OK, ScanRegister::DIFFERS, ScanRegister::MISSING, ScanRegister::BELOW, ScanRegister::DIFFERS], $rows);
        $this->assertSame(
            [ScanRegister::LINKED, ScanRegister::DIFFERS, ScanRegister::UNLINKED, ScanRegister::DIFFERS, ScanRegister::DIFFERS, ScanRegister::UNLINKED],
            array_column($byFile, 'status')
        );
        $this->assertSame([[2], [3], [], [5], [6], []], array_column($byFile, 'lines'));
        // The file that a row goes to is the file that goes back to that row.
        $codes = ScanRegister::checkRows($sheet, $files, self::COLUMNS, ' - ')['codes'];
        $this->assertSame(['ICTCOF1', 'ICTCOF2', '', 'ICTCOF6', 'ICTCOF7'], $codes);
    }
}
