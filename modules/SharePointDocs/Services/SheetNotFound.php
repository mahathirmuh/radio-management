<?php

namespace Modules\SharePointDocs\Services;

use RuntimeException;

/** The workbook has no (worksheet) sheet of that name: renamed, deleted, or a chart. Not the same as a workbook that cannot be read. */
class SheetNotFound extends RuntimeException
{
}
