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

        // A time limit that the code under test gives its own request (CellEdits::approve takes 75 seconds) would
        // go on counting for the tests that follow, and on a slow day end the whole run in the middle of one of them.
        set_time_limit(0);

        // No test reaches the network: a request that is not faked fails the test instead.
        Http::preventStrayRequests();

        // An export "downloaded" in a test is never sent, so its temporary workbook is never
        // removed. Keep those out of the project's storage folder and delete them afterwards.
        $this->excelTemp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'radio-tests-excel-' . getmypid();
        config(['excel.temporary_files.local_path' => $this->excelTemp]);
    }

    /**
     * For a test in which hours or days pass in one signed-in session (a cache, a reminder): the timeouts of the session
     * (SessionTimeoutTest) are not what it is about.
     */
    protected function withoutSessionTimeouts(): void
    {
        config(['session.idle_timeout' => 1_000_000, 'session.absolute_timeout' => 1_000_000]);
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
