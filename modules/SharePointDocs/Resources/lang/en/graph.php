<?php

// Why SharePoint could not be read or written (GraphException), by the reason, in words a person can act on: what
// Microsoft said goes to the log only. "user": a folder or a file; "workbook": a workbook (its other reasons are said
// as "user"); "write": putting a change into a workbook (its other reasons are said as "workbook").

return [
    'user' => [
        'not_configured' => 'The address of the SharePoint folder or the Microsoft application account has not been filled in in the settings (.env).',
        'credentials' => 'The application cannot sign in to Microsoft. Check the tenant, client id and client secret in the settings (the client secret may have expired).',
        'forbidden' => 'The application has no permission to read this folder in SharePoint (it needs the application permission Sites.Read.All with admin consent).',
        'not_found' => 'The folder was not found in SharePoint. It may have been moved or deleted, or the address in the settings is wrong.',
        'not_a_folder' => 'The address in the settings points to a file, not to a folder.',
        'unsupported' => 'This file cannot be shown here (only PDFs and images). Open it in SharePoint.',
        'too_large' => 'This file is too large to be shown here. Open it in SharePoint.',
        'too_slow' => 'Fetching this file from SharePoint took too long (a slow connection or a large file). Open it in SharePoint.',
        'out_of_time' => 'The time for one request ran out before SharePoint could be asked. Reload the page in a moment.',
        'unavailable' => 'SharePoint cannot be reached right now. Try again in a moment.',
    ],

    'workbook' => [
        'not_configured' => 'The address of the workbook or the Microsoft application account has not been filled in in the settings (.env).',
        'not_found' => 'The workbook was not found in SharePoint. It may have been moved, renamed or deleted, or the address in the settings is wrong.',
        'forbidden' => 'The application has no permission to read this workbook in SharePoint (it needs the application permission Sites.Read.All with admin consent).',
        'unsupported' => 'The address in the settings is not an Excel workbook (.xlsx) the application can read (a file with a password cannot be read).',
        'too_large' => 'This workbook is too large to be shown here.',
        'too_slow' => 'Fetching or reading the workbook took too long (a slow connection or a very large workbook). Try again in a moment.',
    ],

    'write' => [
        'forbidden' => 'The application has no permission yet to write to this workbook in SharePoint (it needs the application permission Sites.Selected with write access to this site, or Sites.ReadWrite.All, with admin consent).',
        'changed' => 'Somebody else saved the workbook in SharePoint while this change was being applied. Nothing was written; try approving again.',
        'locked' => 'The workbook is locked because somebody has it open in Excel. Nothing was written; try again in a moment.',
        'not_found' => 'The workbook was not found in SharePoint. It may have been moved, renamed or deleted.',
        'unavailable' => 'SharePoint cannot be reached right now or answers too slowly. Nothing was written; try again in a moment.',
    ],
];
