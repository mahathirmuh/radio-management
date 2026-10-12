<?php

namespace Modules\SharePointDocs\Services;

use RuntimeException;

/**
 * A change of a cell that is refused, with the words the page shows and the HTTP status it is answered with.
 * (Not a failure of Microsoft: that is a GraphException.)
 */
class EditException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
