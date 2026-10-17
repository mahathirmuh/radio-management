<?php

namespace Modules\SharePointDocs\Services;

use RuntimeException;

/** The changed workbook could not be saved on this server (its temporary copy), so nothing was sent to SharePoint. */
class WorkbookNotSaved extends RuntimeException
{
}
