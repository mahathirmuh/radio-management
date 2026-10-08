<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Pages do their work in the browser, which PHPUnit does not run. A checker in tests/tools runs the
 * inline JavaScript of a rendered page under Node.js with a stand-in for jQuery; this renders the
 * page and hands it over (the test is skipped when Node.js is not installed). Needs MakesUsers.
 */
trait RunsPageChecks
{
    /**
     * Renders the page at $url as Super Admin and runs $checker (a file in tests/tools) on it.
     *
     * @param int $least how many checks the checker has to have made: one that checked nothing proves nothing
     */
    private function checkPageScript(string $url, string $checker, int $least = 10): void
    {
        $this->requireNode();

        $page = $this->actingAs($this->superAdmin())->get($url)->assertOk()->getContent();

        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'radio-tests-page-' . getmypid() . '.html';
        try {
            File::put($file, $page);
            $this->runChecker($checker, [$file], $least);
        } finally {
            File::delete($file);
        }
    }

    /** Runs a checker that needs no page (one that checks a tool of the tests itself) and wants at least $least checks made. */
    private function checkTool(string $checker, int $least): void
    {
        $this->requireNode();
        $this->runChecker($checker, [], $least);
    }

    private function requireNode(): void
    {
        $version = new Process(['node', '--version']);
        $version->run();
        if (! $version->isSuccessful()) {
            $this->markTestSkipped('Node.js is not installed.');
        }
        // The checkers use "node:" imports, Array.prototype.at and logical assignment: Node 18 at least.
        if ((int) ltrim(trim($version->getOutput()), 'v') < 18) {
            $this->markTestSkipped('Node.js ' . trim($version->getOutput()) . ' is too old for the checkers (18 or newer).');
        }
    }

    /** @param array<int, string> $arguments */
    private function runChecker(string $checker, array $arguments, int $least): void
    {
        $check = new Process(['node', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $checker, ...$arguments]);
        $check->run();

        $this->assertSame(0, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        $this->assertSame(1, preg_match('/^(\d+) behaviour checks passed$/m', $check->getOutput(), $count), $check->getOutput());
        $this->assertGreaterThanOrEqual($least, (int) $count[1]);
    }
}
