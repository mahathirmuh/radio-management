<?php

namespace Modules\RadioManagement\Services;

use InvalidArgumentException;

/**
 * The picture of a signature as it is kept: what the pad of the browser sent, checked and drawn again here.
 *
 * What comes in is whatever a browser (or somebody without one) sent. It is kept only if it is a PNG of a sensible
 * size with some ink in it, and what is kept is a PNG made HERE from its pixels: nothing else that the file carried
 * (text, a second file after the end of the picture) comes along.
 */
final class SignatureImage
{
    private const PREFIX = 'data:image/png;base64,';

    /** The longest address that is looked at (a signature from the pad is some tens of kilobytes). */
    private const MAX_CHARACTERS = 1_500_000;

    /** The pad makes pictures of 1200 pixels along the longer side. */
    private const MAX_SIDE = 2400;

    /**
     * @param string $address the picture as a data: address ("data:image/png;base64,...")
     * @return string the picture that is kept, as a data: address
     *
     * @throws InvalidArgumentException with the words for the page
     */
    public static function clean(string $address): string
    {
        $refuse = fn (string $why) => new InvalidArgumentException($why);

        if (! str_starts_with($address, self::PREFIX) || strlen($address) > self::MAX_CHARACTERS) {
            throw $refuse('Tanda tangan harus berupa gambar PNG dari kotak tanda tangan.');
        }
        $bytes = base64_decode(substr($address, strlen(self::PREFIX)), true);
        if ($bytes === false || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            throw $refuse('Tanda tangan harus berupa gambar PNG dari kotak tanda tangan.');
        }

        // The size is read from the header first: a picture that says it is enormous is not built up in memory.
        $size = @getimagesizefromstring($bytes);
        if ($size === false || ($size['mime'] ?? '') !== 'image/png' || $size[0] < 8 || $size[1] < 8 || $size[0] > self::MAX_SIDE || $size[1] > self::MAX_SIDE) {
            throw $refuse('Ukuran gambar tanda tangan tidak sesuai.');
        }
        $picture = @imagecreatefromstring($bytes);
        if ($picture === false) {
            throw $refuse('Gambar tanda tangan tidak bisa dibaca.');
        }

        try {
            imagepalettetotruecolor($picture);
            imagealphablending($picture, false);
            imagesavealpha($picture, true);
            if (! self::hasInk($picture)) {
                throw $refuse('Tanda tangan masih kosong.');
            }

            ob_start();
            imagepng($picture, null, 6);
            $png = (string) ob_get_clean();
        } finally {
            imagedestroy($picture);
        }
        if ($png === '') {
            throw $refuse('Gambar tanda tangan tidak bisa disimpan.');
        }

        return self::PREFIX . base64_encode($png);
    }

    /** Whether anything is drawn: a picture that is see-through everywhere is no signature. */
    private static function hasInk(\GdImage $picture): bool
    {
        [$width, $height] = [imagesx($picture), imagesy($picture)];
        // Every pixel of a grid fine enough for the thinnest stroke the pad draws.
        $step = max(1, (int) floor(min($width, $height) / 400));
        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                // The alpha of GD: 0 is solid, 127 is see-through.
                if (((imagecolorat($picture, $x, $y) >> 24) & 0x7F) < 100) {
                    return true;
                }
            }
        }

        return false;
    }
}
