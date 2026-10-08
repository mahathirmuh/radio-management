<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Concerns\RunsPageChecks;

/**
 * The checkers in tests/tools run the scripts of the pages against a stand-in for jQuery. A checker is only as good as the stand-in
 * is like jQuery where the checker leans on it, so the stand-in has a checker of its own.
 */
class PageSandboxTest extends TestCase
{
    use RunsPageChecks;

    public function test_the_stand_in_for_jquery_behaves_like_jquery_where_the_checkers_lean_on_it(): void
    {
        $this->checkTool('tests/tools/page-sandbox-selftest.mjs', 9);
    }
}
