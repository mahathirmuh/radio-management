<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * What changed in each version, as CHANGELOG.md tells it: the dialog that the version in the footer opens. Only the
 * changes are shown. The opening of the file, the lines for whoever runs the server ("**Deploy:** ...") and the links of
 * the headings stay in the file; a link in an entry keeps its words (it points into the repository, not into the app).
 */
final class Changelog
{
    public function __construct(private readonly string $file)
    {
    }

    public static function ofApp(): self
    {
        return new self(base_path('CHANGELOG.md'));
    }

    /**
     * The versions, newest first (the order of the file). "Unreleased" and anything else that is no version number is left
     * out: it has not reached a server yet.
     *
     * @return list<array{version: string, date: ?string, html: string}> html: the changes as markup, any HTML in the file stripped
     */
    public function releases(): array
    {
        if (! is_file($this->file)) {
            return [];
        }
        $text = str_replace("\r\n", "\n", (string) file_get_contents($this->file));
        preg_match_all('/^## \[([^\]\n]+)\][^\n]*$/m', $text, $headings, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $releases = [];
        foreach ($headings as $index => [$line, $version]) {
            $start = $line[1] + strlen($line[0]);
            $end = isset($headings[$index + 1]) ? $headings[$index + 1][0][1] : strlen($text);
            if (! preg_match('/^\d+\.\d+\.\d+$/', $version[0])) {
                continue;
            }
            $releases[] = [
                'version' => $version[0],
                'date' => preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $line[0], $date) ? $date[1] : null,
                'html' => $this->html(substr($text, $start, $end - $start)),
            ];
        }

        return $releases;
    }

    private function html(string $body): string
    {
        // The links of the headings ("[1.10.13]: https://...") are defined at the end of the file, after the last version.
        $body = preg_replace('/^\[[^\]\n]+\]:[ \t]*\S+[ \t]*$/m', '', $body);
        // The note for the server: from "**Deploy:**" to the end of its paragraph.
        $body = preg_replace('/^\*\*Deploy:\*\*.*?(?=\n[ \t]*\n|\z)/ms', '', (string) $body);
        $body = preg_replace('/\[([^\]\n]+)\]\([^)\n]*\)/', '$1', (string) $body);

        return trim(Str::markdown(trim((string) $body), ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }
}
