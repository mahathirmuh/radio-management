<?php

namespace Modules\SharePointDocs\Services;

use RuntimeException;

/**
 * Why a folder or a file of SharePoint could not be shown. The message is the technical detail
 * for the log; the page shows userMessage(), which never contains anything Microsoft sent back
 * (SharePointDocs::graph, in the language of the request unless another is asked for).
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

    public function userMessage(?string $locale = null): string
    {
        return __('SharePointDocs::graph.user.' . match ($this->reason) {
            self::NOT_CONFIGURED, self::CREDENTIALS, self::FORBIDDEN, self::NOT_FOUND, self::NOT_A_FOLDER,
            self::UNSUPPORTED, self::TOO_LARGE, self::TOO_SLOW, self::OUT_OF_TIME => $this->reason,
            default => self::UNAVAILABLE,
        }, [], $locale);
    }

    /** What the page of a workbook says when the workbook cannot be shown (what Microsoft said goes to the log only). */
    public function workbookMessage(?string $locale = null): string
    {
        return match ($this->reason) {
            self::NOT_CONFIGURED, self::NOT_FOUND, self::FORBIDDEN, self::UNSUPPORTED, self::TOO_LARGE, self::TOO_SLOW
                => __('SharePointDocs::graph.workbook.' . $this->reason, [], $locale),
            default => $this->userMessage($locale),
        };
    }

    /** What is said when a change could not be put into the workbook (what Microsoft said goes to the log only). */
    public function writeMessage(?string $locale = null): string
    {
        return match ($this->reason) {
            self::FORBIDDEN, self::CHANGED, self::LOCKED, self::NOT_FOUND => __('SharePointDocs::graph.write.' . $this->reason, [], $locale),
            self::TOO_LARGE, self::UNSUPPORTED, self::NOT_CONFIGURED, self::CREDENTIALS => $this->workbookMessage($locale),
            default => __('SharePointDocs::graph.write.' . self::UNAVAILABLE, [], $locale),
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
