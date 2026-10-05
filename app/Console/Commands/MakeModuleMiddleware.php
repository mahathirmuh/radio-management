<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeModuleMiddleware extends Command
{
    protected $signature = 'make:module-middleware {module} {name}';
    protected $description = 'Create a middleware inside a module';

    public function handle()
    {
        $module = $this->argument('module');
        $name = $this->argument('name');

        $modulePath = base_path("modules/$module");

        if (!File::exists($modulePath)) {
            $this->error("Module '$module' not found!");
            return;
        }

        $path = "$modulePath/Http/Middleware/{$name}.php";
        $namespace = "Modules\\$module\\Http\\Middleware";

        $contents = <<<PHP
        <?php

        namespace $namespace;

        use Closure;
        use Illuminate\Http\Request;

        class $name
        {
            public function handle(Request \$request, Closure \$next)
            {
                return \$next(\$request);
            }
        }
        PHP;

        File::put($path, $contents);
        $this->info("Middleware '$name' created in module '$module'!");
    }
}
