<?php

namespace Tests\Feature;

use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Tests\TestCase;

/**
 * AppServiceProvider::boot() rewrites the input of the request the application was started
 * for. The other feature tests cannot see that: their application is booted before any test
 * request exists. Here an application is started the way the web server does it, with the
 * request already bound.
 */
class RequestSanitizerTest extends TestCase
{
    private function bootFor(Request $request): Request
    {
        try {
            $app = require base_path('bootstrap/app.php');
            $app->instance('request', $request);
            $app->make(Kernel::class)->bootstrap();
            $this->guardAgainstRealDatabase($app);
        } finally {
            // Booting a second application re-points the global container and the facades.
            Container::setInstance($this->app);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($this->app);
        }

        return $request;
    }

    public function test_radio_management_input_reaches_the_controllers_exactly_as_typed(): void
    {
        $request = $this->bootFor(Request::create('/radio-management/master/department', 'POST', [
            'name' => 'E & I <Elektrik>',
            'nested' => ['notes' => 'baterai <50% & antena "longgar"'],
        ]));

        $this->assertSame('E & I <Elektrik>', $request->input('name'));
        $this->assertSame('baterai <50% & antena "longgar"', $request->input('nested.notes'));
    }

    public function test_base_pages_get_tags_stripped_but_text_is_not_entity_encoded(): void
    {
        $request = $this->bootFor(Request::create('/user-management/user-registered', 'POST', [
            'name' => 'Rudi & Tim <b>IT</b>',
            'role_id' => [1, 2],
        ]));

        // "&amp;" stored in the database would be encoded again on every save.
        $this->assertSame('Rudi & Tim IT', $request->input('name'));
        $this->assertSame([1, 2], $request->input('role_id'));
    }
}
