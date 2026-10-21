<?php

namespace Modules\SnipeIt\Services;

use RuntimeException;

/**
 * Snipe-IT gave no answer to show: not set up in .env, not reached, the token refused, or an error. What the person reads
 * is SnipeIt::snipe.errors.<reason>; nothing of the address or the token is in it.
 */
final class SnipeItUnavailable extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured';

    public const UNREACHABLE = 'unreachable';

    public const REFUSED = 'refused';

    public const FAILED = 'failed';

    /** The row asked for is not in Snipe-IT (any more). */
    public const NOT_FOUND = 'not_found';

    public function __construct(public readonly string $reason, public readonly ?int $status = null)
    {
        parent::__construct("Snipe-IT: {$reason}" . ($status !== null ? " ({$status})" : ''));
    }

    /** What the person reads, in the language of the page. */
    public function describe(): string
    {
        return __('SnipeIt::snipe.errors.' . $this->reason, ['status' => (string) $this->status]);
    }
}
