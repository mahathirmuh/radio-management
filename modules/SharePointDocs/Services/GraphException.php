<?php

namespace Modules\SharePointDocs\Services;

use RuntimeException;

/**
 * Why a folder or a file of SharePoint could not be shown. The message is the technical detail
 * for the log; the page shows userMessage(), which never contains anything Microsoft sent back.
 */
class GraphException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const CREDENTIALS = 'credentials';

    public const FORBIDDEN = 'forbidden';

    public const NOT_FOUND = 'not_found';

    public const NOT_A_FOLDER = 'not_a_folder';

    public const UNAVAILABLE = 'unavailable';

    /** A file of a kind that is not shown inside the application. */
    public const UNSUPPORTED = 'unsupported';

    /** A file too large to be shown inside the application. */
    public const TOO_LARGE = 'too_large';

    /** A file whose bytes were arriving when the time allowed for it ran out (a slow line, a big file). */
    public const TOO_SLOW = 'too_slow';

    /**
     * A source that a page reads as its second (see ScanLinks) was not asked at all: what the first took left too little of
     * the time that one request has. Nothing was sent to Microsoft.
     */
    public const OUT_OF_TIME = 'out_of_time';

    /** A file that was put back with the version it was read as, and has been saved by somebody else since (HTTP 412). */
    public const CHANGED = 'changed';

    /** A file that cannot be written now, because somebody has it open in Excel (HTTP 423). */
    public const LOCKED = 'locked';

    /**
     * @param int $status the HTTP status of an answer that is Microsoft's own (Graph's error, a refusal at
     *     the sign-in, the download address); 0 when there was no answer, or one that is not Microsoft's
     * @param bool $outOfTime for "unavailable": it was the time allowed for the whole reading
     *     (GraphClient::within) that ran out. A request was not started any more, or was given up when a
     *     limit that this time had made shorter than usual ran out. That is no sign, by itself, that
     *     Microsoft cannot be reached: SharePointFolder::fileOf() does not hold the whole folder back for
     *     it. (A list or a workbook that is read on that time and runs out of it is held back like any
     *     other "unavailable", except when the time was a window that a page cut out of a bigger one for
     *     a second source and made much shorter than usual: see GraphClient::shared().)
     */
    public function __construct(
        public readonly string $reason,
        string $detail,
        public readonly int $status = 0,
        public readonly bool $outOfTime = false,
    ) {
        parent::__construct($detail);
    }

    public function userMessage(): string
    {
        return match ($this->reason) {
            self::NOT_CONFIGURED => 'Alamat folder SharePoint atau akun aplikasi Microsoft belum diisi di pengaturan (.env).',
            self::CREDENTIALS => 'Aplikasi tidak bisa masuk ke Microsoft. Periksa tenant, client id dan client secret di pengaturan (client secret mungkin sudah kedaluwarsa).',
            self::FORBIDDEN => 'Aplikasi tidak punya izin membaca folder ini di SharePoint (perlu izin aplikasi Sites.Read.All dengan admin consent).',
            self::NOT_FOUND => 'Folder tidak ditemukan di SharePoint. Mungkin sudah dipindahkan atau dihapus, atau alamat di pengaturan keliru.',
            self::NOT_A_FOLDER => 'Alamat di pengaturan menunjuk ke sebuah file, bukan folder.',
            self::UNSUPPORTED => 'File ini tidak bisa ditampilkan di sini (hanya PDF dan gambar). Buka di SharePoint.',
            self::TOO_LARGE => 'File ini terlalu besar untuk ditampilkan di sini. Buka di SharePoint.',
            self::TOO_SLOW => 'File ini terlalu lama diambil dari SharePoint (koneksi lambat atau file besar). Buka di SharePoint.',
            self::OUT_OF_TIME => 'Waktu untuk satu permintaan habis sebelum SharePoint sempat ditanya. Muat ulang halaman beberapa saat lagi.',
            default => 'SharePoint sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.',
        };
    }

    /** What the page of a workbook says when the workbook cannot be shown (what Microsoft said goes to the log only). */
    public function workbookMessage(): string
    {
        return match ($this->reason) {
            self::NOT_CONFIGURED => 'Alamat workbook atau akun aplikasi Microsoft belum diisi di pengaturan (.env).',
            self::NOT_FOUND => 'Workbook tidak ditemukan di SharePoint. Mungkin sudah dipindahkan, diganti namanya atau dihapus, atau alamat di pengaturan keliru.',
            self::FORBIDDEN => 'Aplikasi tidak punya izin membaca workbook ini di SharePoint (perlu izin aplikasi Sites.Read.All dengan admin consent).',
            self::UNSUPPORTED => 'Alamat di pengaturan bukan workbook Excel (.xlsx) yang bisa dibaca aplikasi (file yang diberi password tidak bisa dibaca).',
            self::TOO_LARGE => 'Workbook ini terlalu besar untuk ditampilkan di sini.',
            self::TOO_SLOW => 'Workbook terlalu lama diambil dari SharePoint atau dibaca (koneksi lambat atau workbook sangat besar). Coba lagi beberapa saat lagi.',
            default => $this->userMessage(),
        };
    }

    /** What is said when a change could not be put into the workbook (what Microsoft said goes to the log only). */
    public function writeMessage(): string
    {
        return match ($this->reason) {
            self::FORBIDDEN => 'Aplikasi belum punya izin menulis ke workbook ini di SharePoint (perlu izin aplikasi Sites.Selected dengan akses write untuk site ini, atau Sites.ReadWrite.All, dengan admin consent).',
            self::CHANGED => 'Workbook disimpan orang lain di SharePoint ketika perubahan ini sedang diterapkan. Tidak ada yang ditulis; coba setujui lagi.',
            self::LOCKED => 'Workbook sedang dikunci karena dibuka orang lain di Excel. Tidak ada yang ditulis; coba lagi beberapa saat lagi.',
            self::NOT_FOUND => 'Workbook tidak ditemukan di SharePoint. Mungkin sudah dipindahkan, diganti namanya atau dihapus.',
            self::TOO_LARGE, self::UNSUPPORTED, self::NOT_CONFIGURED, self::CREDENTIALS => $this->workbookMessage(),
            default => 'SharePoint sedang tidak bisa dihubungi atau terlalu lambat menjawab. Tidak ada yang ditulis; coba lagi beberapa saat lagi.',
        };
    }

    /** The status the page answers with. */
    public function httpStatus(): int
    {
        return match ($this->reason) {
            self::NOT_FOUND => 404,
            self::TOO_LARGE => 413,
            self::UNSUPPORTED => 415,
            self::NOT_CONFIGURED, self::UNAVAILABLE => 503,
            self::TOO_SLOW => 504,
            default => 502,
        };
    }
}
