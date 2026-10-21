<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The version of the application (README, section "Versi"): one number in Semantic Versioning, kept in the file
 * VERSION, with its part on top of CHANGELOG.md, and the same wherever it is shown.
 */
class VersionTest extends TestCase
{
    // The regular expression semver.org suggests for Semantic Versioning 2.0.0.
    private const SEMVER = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)'
        . '(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?'
        . '(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

    private function version(): string
    {
        return trim((string) file_get_contents(base_path('VERSION')));
    }

    private function changelog(): string
    {
        return str_replace("\r\n", "\n", (string) file_get_contents(base_path('CHANGELOG.md')));
    }

    public function test_the_version_is_one_semantic_version_in_the_file_version(): void
    {
        $this->assertFileExists(base_path('VERSION'));
        $this->assertMatchesRegularExpression(self::SEMVER, $this->version());
        // The number and a line break, nothing else: a script may read it as it is.
        $this->assertSame($this->version() . "\n", str_replace("\r\n", "\n", (string) file_get_contents(base_path('VERSION'))));
        $this->assertSame($this->version(), config('app.version'));
    }

    public function test_the_changelog_starts_with_the_version_and_has_every_version_once_newest_first(): void
    {
        $heading = '/^## \[([^\]]+)\] - (\d{4}-\d{2}-\d{2})$/m';
        preg_match_all($heading, $this->changelog(), $parts, PREG_SET_ORDER);
        $bodies = array_slice(preg_split($heading, $this->changelog()), 1);
        $this->assertNotEmpty($parts);
        $this->assertSame($this->version(), $parts[0][1], 'CHANGELOG.md starts with the part of the version in VERSION');

        $versions = array_column($parts, 1);
        $this->assertSame(array_values(array_unique($versions)), $versions, 'each version has one part');
        foreach ($parts as $i => [, $version, $date]) {
            $this->assertMatchesRegularExpression(self::SEMVER, $version);
            $this->assertSame($date, date('Y-m-d', strtotime($date)), "the day of {$version}");
            // Each part says what to do on the server, and has its link to what changed.
            $this->assertStringContainsString('**Deploy:**', $bodies[$i], "the part of {$version}");
            $this->assertMatchesRegularExpression('/^\[' . preg_quote($version, '/') . '\]: https:\/\/github\.com\/\S+$/m', $this->changelog());
            if ($i > 0) {
                $this->assertTrue(version_compare($versions[$i - 1], $version, '>'), "{$versions[$i - 1]} comes above {$version}");
                $this->assertGreaterThanOrEqual($date, $parts[$i - 1][2], "{$versions[$i - 1]} is not older than {$version}");
            }
        }
    }

    public function test_the_version_is_shown_in_the_footer_the_api_documentation_and_artisan_about(): void
    {
        $this->assertMatchesRegularExpression('/>\s*v' . preg_quote($this->version(), '/') . '\s*</', view('main.footer.footer')->render());
        $this->assertSame($this->version(), config('scramble.info.version'));

        Artisan::call('about', ['--only' => 'environment', '--json' => true]);
        $this->assertSame($this->version(), json_decode(Artisan::output(), true)['environment']['application_version'] ?? null);

        // No file VERSION (an image built without it): no badge at all rather than a "v" alone.
        config(['app.version' => null]);
        $this->assertStringNotContainsString('badge', view('main.footer.footer')->render());
    }

    public function test_the_docker_image_gets_the_file_version(): void
    {
        // .dockerignore lets in only what it names. The changelog too: the version in the footer opens it.
        $this->assertContains('!VERSION', array_map('trim', file(base_path('.dockerignore'))));
        $this->assertContains('!CHANGELOG.md', array_map('trim', file(base_path('.dockerignore'))));
    }
}
