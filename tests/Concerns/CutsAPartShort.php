<?php

namespace Tests\Concerns;

use ZipArchive;

/**
 * The "zip://" stream wrapper in the place of PHP's own, for a test: it serves the parts of an archive to libxml as PHP's does, and one
 * part can be cut short with a read error, the way a stream breaks off when the compressed bytes of an archive are damaged.
 *
 * cutShort() puts it in place (for one part, after so many bytes), and restore() gives PHP's own wrapper back: call it in a "finally".
 */
final class CutsAPartShort
{
    /** The part that is cut short, and how many bytes of it are served before the read error. */
    private static ?string $part = null;

    private static int $bytes = 0;

    private static bool $installed = false;

    /** Set by PHP: the context the stream was opened with. */
    public $context;

    private string $data = '';

    private int $at = 0;

    private bool $cut = false;

    public static function cutShort(?string $part, int $bytes = 0): void
    {
        self::$part = $part;
        self::$bytes = $bytes;
        if (! self::$installed) {
            stream_wrapper_unregister('zip');
            stream_wrapper_register('zip', self::class);
            self::$installed = true;
        }
    }

    public static function restore(): void
    {
        self::$part = null;
        if (self::$installed) {
            stream_wrapper_restore('zip');
            self::$installed = false;
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool
    {
        [$file, $part] = explode('#', substr($path, strlen('zip://')), 2) + [1 => ''];
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            return false;
        }
        $data = $zip->getFromName($part);
        $zip->close();
        if ($data === false) {
            return false;
        }
        $this->cut = self::$part !== null && $part === self::$part;
        $this->data = $this->cut ? substr($data, 0, self::$bytes) : $data;

        return true;
    }

    public function stream_read(int $count): string|false
    {
        if ($this->at >= strlen($this->data)) {
            // A part that is cut short ends with an error, not with the end of the file.
            return $this->cut ? false : '';
        }
        $chunk = substr($this->data, $this->at, $count);
        $this->at += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return ! $this->cut && $this->at >= strlen($this->data);
    }

    public function stream_stat(): array|false
    {
        return [];
    }

    /** PHP asks for it before it opens a file for libxml: without it the part is "not found". */
    public function url_stat(string $path, int $flags): array|false
    {
        return [];
    }
}
