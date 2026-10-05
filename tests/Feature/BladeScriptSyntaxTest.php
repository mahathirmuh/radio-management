<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The pages carry their JavaScript inline in the Blade views, where PHPUnit never runs it:
 * one unterminated string leaves a whole page without its buttons while every other test is
 * green. This parses each inline <script> block with Node.js (skipped when Node is absent).
 */
class BladeScriptSyntaxTest extends TestCase
{
    private const CHECKER = 'tests/tools/blade-script-syntax.mjs';

    protected function setUp(): void
    {
        parent::setUp();

        $version = new Process(['node', '--version']);
        $version->run();
        if (! $version->isSuccessful()) {
            $this->markTestSkipped('Node.js is not installed.');
        }
    }

    private function check(string $root): Process
    {
        $check = new Process(['node', base_path(self::CHECKER), $root]);
        $check->run();

        return $check;
    }

    public function test_every_inline_script_in_the_views_is_valid_javascript(): void
    {
        $check = $this->check(base_path());

        $this->assertSame(0, $check->getExitCode(), $check->getOutput() . $check->getErrorOutput());
        $this->assertStringContainsString('no syntax errors', $check->getOutput());
        // A checker that finds nothing to parse proves nothing.
        $this->assertMatchesRegularExpression('/ [1-9]\d+ inline script blocks parsed/', $check->getOutput());
    }

    /** The checker itself: what it must report, and the Blade it must not trip over. */
    public function test_the_checker_reports_broken_scripts_and_accepts_blade_inside_valid_ones(): void
    {
        $cases = [
            // view source => must be reported
            'valid-blade' => ["<script>\nconst url = \"{{ route('x') }}\";\nconst rows = @json(['a' => 'b ) (', 'n' => count(\$x)]);\n"
                . "let mode;\n@if (\$canEdit)\n mode = 1;\n@elseif (str_contains(\$x, ')'))\n mode = 2;\n@else\n mode = 3;\n@endif\n"
                . "@php\n \$ids = collect(\$rows)->map(fn (\$r) => \$r->id);\n@endphp\n"
                . "const ids = [@foreach (\$ids as \$id) {{ \$id }}, @endforeach];\n"
                . "const list = [];\n@forelse (\$rows as \$row) list.push({{ \$row }}); @empty list.push(0); @endforelse\n</script>\n"
                . "<script type=\"text/template\"><div>{{ not javascript }</div></script>\n"
                . "<script type=\"module\">import x from './x.js';</script>", false],
            'line-break-in-string' => ["<script>\nSwal.fire({ text: reasons.join(\"\n\") });\n</script>", true],
            'error-on-a-directive-line' => ["<script>\n@if (\$canEdit) $('#btn').on('click', () => save(\"x); @endif\n</script>", true],
            'declared-twice' => ["<script>\nconst a = 1;\nconst a = 2;\n</script>", true],
            'declared-twice-next-to-a-directive' => ["<script>\nconst a = 1;\nconst a = 2;\n@if (\$x) console.log(1); @endif\n</script>", true],
            'data-attributes-are-not-type-or-src' => ["<script data-type=\"x\" data-src=\"y\">\nconst b = \"x;\n</script>", true],
            'missing-brace' => ["<script type=\"text/javascript\">\nfunction f() {\n  return 1;\n</script>", true],
            // An argument list the checker cannot close must not make it skip the rest of the script.
            'quote-in-a-directive-comment' => ["<script>\nconst rows = @json(\$rows /* the user's rows */);\nconst a = \"x;\n</script>", true],
            'directive-named-in-a-js-comment' => ["<script>\n// TODO: switch this to @json(\nfoo(;\n</script>", true],
            'end-tag-with-a-space' => ["<script>\nconst a = \"x;\n</script >\n<p>text</p>", true],
            'empty-type-is-javascript' => ["<script type=\"\">\nconst a = \"x;\n</script>", true],
            'legacy-type-is-javascript' => ["<script type=\"application/x-javascript\">\nconst a = \"x;\n</script>", true],
        ];

        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'radio-tests-views-' . getmypid();
        try {
            foreach ($cases as $name => [$source, $broken]) {
                File::deleteDirectory($root);
                File::ensureDirectoryExists($root . '/resources/views');
                File::put($root . "/resources/views/{$name}.blade.php", $source);

                $check = $this->check($root);

                $this->assertSame($broken ? 1 : 0, $check->getExitCode(), "{$name}: " . $check->getOutput() . $check->getErrorOutput());
            }
        } finally {
            File::deleteDirectory($root);
        }
    }
}
