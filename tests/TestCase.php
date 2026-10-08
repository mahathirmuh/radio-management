<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    private ?string $excelTemp = null;

    protected function setUp(): void
    {
        parent::setUp();

        // No test reaches the network: a request that is not faked fails the test instead.
        Http::preventStrayRequests();

        // An export "downloaded" in a test is never sent, so its temporary workbook is never
        // removed. Keep those out of the project's storage folder and delete them afterwards.
        $this->excelTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'radio-tests-excel-' . getmypid();
        config(['excel.temporary_files.local_path' => $this->excelTemp]);
    }

    protected function tearDown(): void
    {
        try {
            // null when setUp() itself failed before the folder was chosen
            if ($this->excelTemp !== null) {
                foreach (glob($this->excelTemp . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($this->excelTemp);
            }
        } finally {
            parent::tearDown();
        }
    }
}
