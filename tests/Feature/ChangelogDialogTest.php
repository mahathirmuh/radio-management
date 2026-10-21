<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * The version in the footer opens what changed in each version (CHANGELOG.md): for everyone signed in, whatever their role,
 * every version of the file newest first, without the notes for the server.
 */
class ChangelogDialogTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

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
        foreach ($answer['releases'] as $release) {
            $this->assertMatchesRegularExpression('/^\d{1,2} \p{L}{3} \d{4}$/u', (string) $release['date_label'], $release['version']);
            $this->assertNotSame('', $release['html'], $release['version']);
            $this->assertStringNotContainsString('Deploy:', $release['html'], $release['version']);
            $this->assertStringNotContainsString('<a ', $release['html'], $release['version']);
        }
    }

    public function test_the_footer_has_the_version_as_a_button_and_the_dialog_it_opens(): void
    {
        $page = (string) $this->actingAs($this->superAdmin())->get(route('dashboard.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<button type="button" class="rm-version" id="rmVersion"', $page);
        $this->assertStringContainsString('>v' . config('app.version') . '</button>', $page);
        $this->assertSame(1, substr_count($page, 'id="rmChangelog"'));
        $this->assertStringContainsString('url: "' . str_replace('/', '\/', route('changelog')) . '"', $page);
    }

    public function test_the_script_of_the_dialog(): void
    {
        $this->checkPageScript(route('dashboard.index'), 'tests/tools/changelog-behaviour.mjs', 5);
    }
}
