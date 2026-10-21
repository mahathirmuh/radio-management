<?php

namespace Tests\Unit;

use App\Services\Changelog;
use PHPUnit\Framework\TestCase;

/**
 * What changed in each version, read from a CHANGELOG.md for the dialog of the footer's version: only the changes, newest
 * first, as markup without anything the file could slip in.
 */
class ChangelogTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'changelog-');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    /** @return list<array{version: string, date: ?string, html: string}> */
    private function read(string $text): array
    {
        file_put_contents($this->file, $text);

        return (new Changelog($this->file))->releases();
    }

    public function test_the_versions_newest_first_with_their_dates_and_only_their_changes(): void
    {
        $releases = $this->read(<<<'MD'
            # Changelog

            The opening, about [Keep a Changelog](https://keepachangelog.com/) and the **Deploy** lines.

            ## [Unreleased]

            - Not on a server yet.

            ## [1.2.0] - 2026-10-21

            ### Ditambahkan

            - A dialog, see [the README](README.md#versi).
              - with `code` in it

            **Deploy:** tanpa migrasi,
            tanpa setting baru.

            ## [1.1.0] - 2026-10-17

            ### Diperbaiki

            - Something.

            **Deploy:** `php artisan migrate`.

            [1.2.0]: https://github.com/example/app/compare/v1.1.0...v1.2.0
            [1.1.0]: https://github.com/example/app/compare/v1.0.0...v1.1.0
            MD);

        $this->assertSame(['1.2.0', '1.1.0'], array_column($releases, 'version'));
        $this->assertSame(['2026-10-21', '2026-10-17'], array_column($releases, 'date'));

        $newest = $releases[0]['html'];
        $this->assertStringContainsString('<h3>Ditambahkan</h3>', $newest);
        $this->assertStringContainsString('<li>A dialog, see the README.', $newest);
        $this->assertStringContainsString('<code>code</code>', $newest);
        foreach ([$newest, $releases[1]['html']] as $html) {
            // Neither the note for the server, nor a link (they point into the repository), nor the opening of the file.
            $this->assertStringNotContainsString('Deploy', $html);
            $this->assertStringNotContainsString('migrat', $html);
            $this->assertStringNotContainsString('<a ', $html);
            $this->assertStringNotContainsString('github.com', $html);
            $this->assertStringNotContainsString('Keep a Changelog', $html);
        }
        $this->assertSame("<h3>Diperbaiki</h3>\n<ul>\n<li>Something.</li>\n</ul>", $releases[1]['html']);
    }

    public function test_html_in_the_file_is_not_passed_on(): void
    {
        $releases = $this->read("## [1.0.0] - 2026-10-01\n\n- Fine <script>alert(1)</script> <img src=x onerror=alert(1)>\n");

        $this->assertStringNotContainsString('<script', $releases[0]['html']);
        $this->assertStringNotContainsString('<img', $releases[0]['html']);
        $this->assertStringContainsString('<li>Fine', $releases[0]['html']);
    }

    public function test_a_version_without_a_date_and_a_missing_file(): void
    {
        $this->assertSame([['version' => '1.0.0', 'date' => null, 'html' => '<ul>
<li>First.</li>
</ul>']], $this->read("## [1.0.0]\n\n- First.\n"));

        $this->assertSame([], (new Changelog($this->file . '-gone'))->releases());
    }
}
