<?php

namespace Modules\RadioManagement\Services;

use Exception;

/**
 * An import of the Excel register that did not happen, with what to say about it: refused (the file is not what the
 * importer expects: 422, nothing changed) or failed (the server: 500, nothing changed, the details are in the log).
 */
class ImportProblem extends Exception
{
    public function __construct(public readonly string $title, string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function refused(string $message): self
    {
        return new self('Impor dibatalkan', $message, 422);
    }

    public static function failed(string $message): self
    {
        return new self('Impor gagal', $message, 500);
    }
}
