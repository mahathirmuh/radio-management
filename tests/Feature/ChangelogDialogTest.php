<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\Concerns\UsesEnglish;
use Tests\TestCase;

/**
 * The version in the footer opens what changed in each version (CHANGELOG.md): for everyone signed in, whatever their role,
 * every version of the file newest first, without the notes for the server, in the language of the page (the English notes
 * are in CHANGELOG.en.md).
 */
class ChangelogDialogTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;
    use UsesEnglish;

    protected bool $seed = true;

    public function test_only_who_is_signed_in_reads_it(): void
    {
        $this->getJson(route('changelog'))->assertUnauthorized();
        $this->get(route('changelog'))->assertRedirect(route('login'));
    }

    public function test_every_version_of_the_file_newest_first_and_the_running_one_marked(): void
    {
        // Any role: a role that may only read sees it too (no menu stands for it).
        $this->actingAs($this->makeUser('staf@test.local', 'Viewer Radio'));
        $answer = $this->getJson(route('changelog'))->assertOk()->json();

        preg_match_all('/^## \[(\d+\.\d+\.\d+)\]/m', (string) file_get_contents(base_path('CHANGELOG.md')), $versions);
        $this->assertSame($versions[1], array_column($answer['releases'], 'version'));
        $this->assertSame(config('app.version'), $answer['version']);
        $this->assertSame(config('app.version'), $answer['releases'][0]['version']);
        $this->assertSame([0], array_keys(array_filter(array_column($answer['releases'], 'current'))));
        $this->assertSame(['id'], array_values(array_unique(array_column($answer['releases'], 'language'))));
        foreach ($answer['releases'] as $release) {
            $this->assertMatchesRegularExpression('/^\d{1,2} \p{L}{3} \d{4}$/u', (string) $release['date_label'], $release['version']);
            $this->assertNotSame([], $release['sections'], $release['version']);
            foreach ($release['sections'] as $section) {
                $this->assertSame(['heading', 'kind', 'html'], array_keys($section), $release['version']);
                $this->assertContains($section['kind'], [null, 'added', 'changed', 'fixed', 'removed', 'security', 'deprecated'], $release['version']);
                $this->assertNotSame('', $section['html'], $release['version']);
                $this->assertStringNotContainsString('Deploy:', $section['html'], $release['version']);
                $this->assertStringNotContainsString('<a ', $section['html'], $release['version']);
            }
        }
        // Every heading of the file is a kind the dialog colours: a new heading would come out grey.
        $headings = array_filter(array_merge(...array_map(fn (array $release) => array_column($release['sections'], 'heading'), $answer['releases'])));
        $this->assertSame([], array_values(array_unique(array_filter($headings, fn (string $heading) => ! isset(\App\Services\Changelog::KINDS[mb_strtolower($heading)])))));
    }

    public function test_in_english_every_version_has_its_notes_in_english(): void
    {
        $this->actingAs($this->makeUser('staf@test.local', 'Viewer Radio'));
        $indonesian = $this->getJson(route('changelog'))->assertOk()->json('releases');
        $this->useEnglish();
        $english = $this->getJson(route('changelog'))->assertOk()->json('releases');

        // CHANGELOG.en.md has every version of CHANGELOG.md with the same kinds of change: a release written in one file fails here.
        $this->assertSame(array_column($indonesian, 'version'), array_column($english, 'version'));
        foreach ($english as $index => $release) {
            $this->assertSame('en', $release['language'], $release['version'] . ' has no notes in CHANGELOG.en.md');
            $this->assertSame(array_column($indonesian[$index]['sections'], 'kind'), array_column($release['sections'], 'kind'), $release['version']);
            $this->assertSame($indonesian[$index]['date'], $release['date'], $release['version']);
            $this->assertMatchesRegularExpression('/^\d{1,2} [A-Z][a-z]{2} \d{4}$/', (string) $release['date_label'], $release['version']);
            foreach ($release['sections'] as $section) {
                $this->assertNotSame('', $section['html'], $release['version']);
                $this->assertStringNotContainsString('Deploy:', $section['html'], $release['version']);
                $this->assertStringNotContainsString('<a ', $section['html'], $release['version']);
            }
        }
        // Nor does CHANGELOG.en.md have a version that CHANGELOG.md has not (a typo in a number would go unseen).
        preg_match_all('/^## \[([^\]\n]+)\]/m', (string) file_get_contents(base_path('CHANGELOG.en.md')), $versions);
        $this->assertSame(array_column($indonesian, 'version'), $versions[1]);
    }

    public function test_the_footer_has_the_version_as_a_button_and_the_dialog_it_opens(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<button type="button" class="rm-version" id="rmVersion"', $page);
        $this->assertStringContainsString('>v' . config('app.version') . '</button>', $page);
        $this->assertSame(1, substr_count($page, 'id="rmChangelog"'));
        $this->assertStringContainsString('<p class="rm-dialog-sub">Versi yang berjalan: v' . config('app.version') . '</p>', $page);
        $this->assertStringContainsString('url: "' . str_replace('/', '\/', route('changelog')) . '"', $page);
    }

    public function test_the_script_of_the_dialog(): void
    {
        $this->checkPageScript(route('dashboard.index'), 'tests/tools/changelog-behaviour.mjs', 8);
    }
}
