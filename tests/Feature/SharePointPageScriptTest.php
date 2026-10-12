<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesUsers;
use Tests\Concerns\RunsPageChecks;
use Tests\TestCase;

/**
 * The SharePoint pages do their work in the browser: they escape what comes from SharePoint,
 * decide which request a search sends and which answer is shown, drive the file viewer and the
 * tabs of a workbook. PHPUnit does not run that JavaScript, so this renders each page and hands
 * it to a checker in tests/tools, which runs the page's script under Node.js with a stand-in for
 * jQuery (skipped when Node is absent).
 */
class SharePointPageScriptTest extends TestCase
{
    use MakesUsers;
    use RefreshDatabase;
    use RunsPageChecks;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.azure.tenant' => 'tenant-1',
            'services.azure.client_id' => 'client-1',
            'services.azure.client_secret' => 'secret-1',
            'sharepoint-docs.folders.cof-scan.url' => 'https://contoso.sharepoint.com/sites/Team/Shared%20Documents/Forms/AllItems.aspx?id=%2Fsites%2FTeam%2FScans&viewid=1',
            'sharepoint-docs.workbooks.ict-masterlist.url' => 'https://contoso.sharepoint.com/sites/Team/_layouts/15/Doc.aspx?sourcedoc=%7B1%7D&file=Register.xlsx',
            // The workbook's COF sheet lists the files of the folder: the page of the folder is made with the columns of that sheet.
            'sharepoint-docs.workbooks.ict-masterlist.scans' => ['COF' => ['folder' => 'cof-scan', 'link' => 'Tautan', 'code' => 'COF No', 'note' => 'Catatan']],
        ]);
    }

    public function test_the_script_of_the_folder_page_behaves_as_described(): void
    {
        $this->checkPageScript(route('documents.cof-scan.index'), 'tests/tools/sharepoint-page-behaviour.mjs', 25);
    }

    public function test_the_script_of_the_workbook_page_behaves_as_described(): void
    {
        $this->checkPageScript(route('documents.ict-masterlist.index'), 'tests/tools/sharepoint-workbook-behaviour.mjs', 36);
    }

    public function test_the_script_of_the_page_of_approvals_behaves_as_described(): void
    {
        $this->checkPageScript(route('documents.edit-approvals.index'), 'tests/tools/sharepoint-approvals-behaviour.mjs', 13);
    }
}
