<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * What changed in each version, as CHANGELOG.md tells it: the dialog that the version in the footer opens. Only the
 * changes are shown, by the kind of change each heading of a version names ("### Ditambahkan"). The opening of the file,
 * the lines for whoever runs the server ("**Deploy:** ...") and the links of the headings stay in the file; a link in an
 * entry keeps its words (it points into the repository, not into the app). The file is written in Indonesian; the same
 * notes in another language are in a file beside it (CHANGELOG.en.md).
 */
final class Changelog
{
    /** The language of the file. Its notes in another language are in CHANGELOG.<language>.md beside it. */
    public const LANGUAGE = 'id';

    /** The kind of change a heading names (Keep a Changelog, in Indonesian as the file writes it, or in English). */
    public const KINDS = [
        'ditambahkan' => 'added',
        'added' => 'added',
        'diubah' => 'changed',
        'changed' => 'changed',
        'diperbaiki' => 'fixed',
        'fixed' => 'fixed',
        'dihapus' => 'removed',
        'removed' => 'removed',
        'keamanan' => 'security',
        'security' => 'security',
        'ditinggalkan' => 'deprecated',
        'usang' => 'deprecated',
        'deprecated' => 'deprecated',
    ];

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
     * @return list<array{version: string, date: ?string, sections: list<array{heading: ?string, kind: ?string, html: string}>}>
     *     sections: the changes by their heading, in the order of the file; kind: added, changed, fixed, removed, security,
     *     deprecated, or null for a heading of another kind (and for notes before the first heading, which have none); html:
     *     the notes as markup, any HTML in the file stripped
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
                'sections' => $this->sections(substr($text, $start, $end - $start)),
            ];
        }

        return $releases;
    }

    /**
     * The versions with their notes in a language: for each version, the notes of the file in that language when it has
     * the version, else those of this file. The versions and their dates are always this file's.
     *
     * @return list<array{version: string, date: ?string, sections: list<array{heading: ?string, kind: ?string, html: string}>, language: string}>
     *     language: the language the notes of the version are written in
     */
    public function releasesIn(string $language): array
    {
        $translated = [];
        if ($language !== self::LANGUAGE && preg_match('/^[a-z]{2}$/', $language)) {
            $translation = new self(preg_replace('/\.md$/', '', $this->file) . ".$language.md");
            $translated = array_column($translation->releases(), 'sections', 'version');
        }

        return array_map(fn (array $release) => isset($translated[$release['version']])
            ? array_replace($release, ['sections' => $translated[$release['version']]]) + ['language' => $language]
            : $release + ['language' => self::LANGUAGE], $this->releases());
    }

    /**
     * The changes of a version by their heading ("### Ditambahkan"): the kind of change, the notes as markup. A heading
     * without notes is left out.
     *
     * @return list<array{heading: ?string, kind: ?string, html: string}>
     */
    private function sections(string $body): array
    {
        $parts = preg_split('/^###[ \t]+(.+?)[ \t]*$/m', $this->changes($body), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $sections = [];
        $lead = $this->html((string) array_shift($parts));
        if ($lead !== '') {
            $sections[] = ['heading' => null, 'kind' => null, 'html' => $lead];
        }
        foreach (array_chunk($parts, 2) as $pair) {
            [$heading, $notes] = $pair + [1 => ''];
            $heading = trim(strip_tags((string) $heading));
            $html = $this->html((string) $notes);
            if ($heading !== '' && $html !== '') {
                $sections[] = ['heading' => $heading, 'kind' => self::KINDS[Str::lower($heading)] ?? null, 'html' => $html];
            }
        }

        return $sections;
    }

    /** The body of a version without what is not a change: the links of the headings, the note for the server, the links. */
    private function changes(string $body): string
    {
        // The links of the headings ("[1.10.13]: https://...") are defined at the end of the file, after the last version.
        $body = preg_replace('/^\[[^\]\n]+\]:[ \t]*\S+[ \t]*$/m', '', $body);
        // The note for the server: from "**Deploy:**" to the end of its paragraph.
        $body = preg_replace('/^\*\*Deploy:\*\*.*?(?=\n[ \t]*\n|\z)/ms', '', (string) $body);

        return (string) preg_replace('/\[([^\]\n]+)\]\([^)\n]*\)/', '$1', (string) $body);
    }

    private function html(string $markdown): string
    {
        return trim(Str::markdown(trim($markdown), ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }
}
