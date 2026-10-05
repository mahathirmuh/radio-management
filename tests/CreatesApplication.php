<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->guardAgainstRealDatabase($app);

        return $app;
    }

    /**
     * The tests wipe their database (RefreshDatabase). phpunit.xml points them at a
     * dedicated "... Test" database, but a cached config (php artisan config:cache)
     * or a changed phpunit.xml would silently send them to the database in .env.
     */
    protected function guardAgainstRealDatabase(Application $app): void
    {
        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        if ($database !== ':memory:' && ! str_ends_with(strtolower($database), 'test')) {
            throw new RuntimeException(
                "Refusing to run tests against database \"{$database}\": its name must end with \"Test\". "
                . 'Check phpunit.xml and run `php artisan config:clear`.'
            );
        }
    }
}
