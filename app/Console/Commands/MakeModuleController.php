<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeModuleController extends Command
{
    protected $signature = 'make:module-controller {module} {name}';
    protected $description = 'Create a new controller inside a module';

    public function handle()
    {
        $moduleName = $this->argument('module');
        $controllerName = $this->argument('name');

        if (empty($moduleName) || empty($controllerName)) {
            $this->error('Module name and Controller name are required!');
            return;
        }

        $this->createController($moduleName, $controllerName);
    }

    private function createController($moduleName, $controllerName)
    {
        $modulePath = base_path("modules/$moduleName");

        if (!File::exists($modulePath)) {
            $this->error("Module '$moduleName' not found! Please create the module first.");
            return;
        }

        $path = "$modulePath/Http/Controllers/{$controllerName}.php";

        if (File::exists($path)) {
            $this->error("Controller '$controllerName' already exists in module '$moduleName'!");
            return;
        }

        $namespace = "Modules\\$moduleName\\Http\\Controllers";
        $contents = <<<PHP
        <?php

        namespace $namespace;

        use App\Http\Controllers\Controller;
        use Illuminate\Http\Request;

        class $controllerName extends Controller
        {
            public function index()
            {
                return response()->json(['message' => '$controllerName Controller in $moduleName module']);
            }
        }
        PHP;

        File::ensureDirectoryExists(dirname($path), 0755, true);
        File::put($path, $contents);

        $this->info("Controller '$controllerName' created inside module '$moduleName'!");
    }
}
