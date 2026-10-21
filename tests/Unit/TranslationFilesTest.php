<?php

namespace Tests\Unit;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every text of the screens is there in both languages (lang/<code>/, modules/<Module>/Resources/lang/<code>/): the same
 * files, the same keys and the same :placeholders on both sides, no key written twice; and Laravel's own messages are
 * complete in Indonesian (the English ones come with the framework).
 */
class TranslationFilesTest extends TestCase
{
    /** Laravel's own files: in lang/id only. */
    private const FRAMEWORK = ['auth.php', 'pagination.php', 'passwords.php', 'validation.php'];

    /** @return array<string, array{0: string}> each folder of the languages: the application's, and each module's */
    public static function folders(): array
    {
        $root = dirname(__DIR__, 2);
        $folders = ['lang' => [$root . '/lang']];
        foreach (glob($root . '/modules/*/Resources/lang') as $folder) {
            $folders[basename(dirname($folder, 2))] = [$folder];
        }

        return $folders;
    }

    #[DataProvider('folders')]
    public function test_every_text_is_there_in_both_languages_with_the_same_placeholders(string $folder): void
    {
        $files = fn (string $code) => array_values(array_diff(array_map('basename', glob("{$folder}/{$code}/*.php")), self::FRAMEWORK));
        $this->assertSame($files('id'), $files('en'), 'the same files in both languages');
        $this->assertNotSame([], $files('id'));

        foreach ($files('id') as $file) {
            $id = self::flatten(require "{$folder}/id/{$file}");
            $en = self::flatten(require "{$folder}/en/{$file}");
            $this->assertSame([], array_values(array_diff(array_keys($id), array_keys($en))), "{$file}: only in Indonesian");
            $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($id))), "{$file}: only in English");
            foreach ($id as $key => $text) {
                $this->assertSame(self::placeholders($text), self::placeholders($en[$key]), "{$file}: {$key}");
                $this->assertNotSame('', trim($text), "{$file}: {$key} is empty");
            }
        }
    }

    /** A key written twice in one array: PHP keeps the last without a word, and the text of the first is lost. */
    #[DataProvider('folders')]
    public function test_no_key_is_written_twice(string $folder): void
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $twice = [];
        foreach (glob("{$folder}/*/*.php") as $file) {
            foreach ((new NodeFinder())->findInstanceOf($parser->parse(file_get_contents($file)), Array_::class) as $array) {
                $lines = [];
                foreach ($array->items as $item) {
                    if (! $item?->key instanceof String_) {
                        continue;
                    }
                    $key = $item->key->value;
                    if (isset($lines[$key])) {
                        $twice[] = basename(dirname($file)) . '/' . basename($file) . ": '{$key}' on lines {$lines[$key]} and {$item->getStartLine()}";
                    }
                    $lines[$key] = $item->getStartLine();
                }
            }
        }

        $this->assertSame([], $twice);
    }

    public function test_laravels_own_messages_are_all_there_in_indonesian(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (self::FRAMEWORK as $file) {
            $en = self::flatten(require "{$root}/vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}");
            $id = self::flatten(require "{$root}/lang/id/{$file}");
            $en = array_filter($en, fn (string $key) => ! str_starts_with($key, 'custom.'), ARRAY_FILTER_USE_KEY);
            $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($id))), "{$file}: not in Indonesian");
            foreach ($en as $key => $text) {
                $this->assertSame(self::placeholders($text), self::placeholders($id[$key]), "{$file}: {$key}");
            }
        }
    }

    /** @return array<string, string> "a.b.c" => text */
    private static function flatten(array $texts, string $prefix = ''): array
    {
        $flat = [];
        foreach ($texts as $key => $text) {
            if (is_array($text)) {
                $flat += self::flatten($text, $prefix . $key . '.');
            } else {
                $flat[$prefix . $key] = (string) $text;
            }
        }

        return $flat;
    }

    /** @return list<string> the :placeholders of a text, each once, in lower case (":Attribute" is ":attribute" capitalised) */
    private static function placeholders(string $text): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $text, $found);
        $names = array_values(array_unique(array_map('strtolower', $found[1])));
        sort($names);

        return $names;
    }
}
