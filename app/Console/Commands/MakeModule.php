<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeModule extends Command
{
    protected $signature = 'make:module';
    protected $description = 'Create a new module structure';

    public function handle()
    {
        $moduleName = $this->ask('What is the module name?');
        if (empty($moduleName)) {
            $this->error('Module name cannot be empty!');
            return;
        }

        $this->createDirectories($moduleName);
        $this->info("Module '$moduleName' directory created!");

        $this->createController($moduleName);
        $this->info("Module '$moduleName' controller created!");

        $this->createMiddleware($moduleName);
        $this->info("Middleware structure for '$moduleName' created!");

        $this->createRequests($moduleName);
        $this->info("Request validation for '$moduleName' created!");

        $this->createModels($moduleName);
        $this->info("Model for '$moduleName' created!");

        $this->createRepositories($moduleName);
        $this->info("Repository pattern implemented for '$moduleName'!");

        $this->createViews($moduleName);
        $this->info("Views directory for '$moduleName' initialized!");

        $this->createAssets($moduleName);
        $this->info("Assets folder for '$moduleName' created!");

        $this->createRoutes($moduleName);
        $this->info("Routes file for '$moduleName' generated!");

        $this->createProvider($moduleName);
        $this->info("Service provider for '$moduleName' registered!");

        $this->registerRoutes($moduleName);
        $this->info("Routes for '$moduleName' successfully registered!");

        $this->registerProvider($moduleName);
        $this->info("Provider for '$moduleName' successfully registered!");

        $this->info("Module '$moduleName' created successfully!");
    }

    private function createDirectories($moduleName)
    {
        $paths = [
            "modules/$moduleName/Http/Controllers",
            "modules/$moduleName/Http/Middleware",
            "modules/$moduleName/Http/Requests",
            "modules/$moduleName/Models",
            "modules/$moduleName/Repositories",
            "modules/$moduleName/Providers",
            "modules/$moduleName/Resources/views",
            "modules/$moduleName/Resources/js",
            "modules/$moduleName/Resources/css",
            "modules/$moduleName/Routes/web",
            "modules/$moduleName/Routes/api",
        ];

        foreach ($paths as $path) {
            File::makeDirectory(base_path($path), 0755, true, true);
        }
    }

    private function createController($moduleName)
    {
        // Convert camelCase or PascalCase to kebab-case
        $routeName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $moduleName));
        $routeName = str_replace(['_', ' '], '-', $routeName); // Ensure underscores and spaces are replaced

        $path = base_path("modules/$moduleName/Http/Controllers/{$moduleName}Controller.php");
        $contents = <<<PHP
        <?php
    
        namespace Modules\\$moduleName\\Http\\Controllers;
    
        use App\\Http\\Controllers\\Controller;
        use Illuminate\\Http\\Request;
        use App\\Models\\UserManagement\\Company;
        use App\\Models\\UserManagement\\Menu;
        use App\\Models\\UserManagement\\Permission;
        use App\\Models\\UserManagement\\Role;
        use App\Models\UserManagement\RoleUser;
        use App\\Models\\UserManagement\\Site;
        use App\\Models\\UserManagement\\User;
        use Exception;
        use Illuminate\\Support\\Facades\\Auth;
        use Illuminate\\Support\\Facades\\DB;
        use Illuminate\\Support\\Facades\\Validator;
        use Yajra\\DataTables\\Facades\\DataTables;
    
        class {$moduleName}Controller extends Controller
        {
            protected \$pageTitle;
            protected \$menuItems;
            private \$user;
            private \$company;
            private \$role;
            private \$roleUser;
            private \$menuId;
    
            public function __construct()
            {
                \$this->menuId = 0; // Menu must be registered
                \$this->pageTitle = "{$moduleName}";
                \$this->menuItems = [
                    ['url' => '/', 'label' => '{$routeName}', 'active' => false],
                    ['url' => '/{$routeName}', 'label' => '{$routeName}', 'active' => true],
                    ['url' => '/{$routeName}', 'label' => '{$routeName}', 'active' => true],
                ];
                \$this->user = new User();
                \$this->company = new Company();
                \$this->role = new Role();
                \$this->roleUser = new RoleUser();
            }

            /**
            * Show the application dashboard.
            *
            * @return \Illuminate\Contracts\Support\Renderable
            */
            public function index()
            {
                try {
                    return view('$moduleName::index', [
                        'pageTitle' => \$this->pageTitle,
                        'menuItems' => \$this->menuItems,
                        'companies' => \$this->company,
                        'roles' => '\$this->roles',
                    ]);
                } catch (Exception \$e) {
                    throw \$e;
                }
            }
        }
        PHP;
        File::put($path, $contents);
    }

    private function createMiddleware($moduleName)
    {
        $path = base_path("modules/$moduleName/Http/Middleware/{$moduleName}Middleware.php");
        $contents = <<<PHP
        <?php

        namespace Modules\\$moduleName\\Http\\Middleware;

        use Closure;
        use Illuminate\\Http\\Request;

        class {$moduleName}Middleware
        {
            public function handle(Request \$request, Closure \$next)
            {
                return \$next(\$request);
            }
        }
        PHP;
        File::put($path, $contents);
    }

    private function createRequests($moduleName)
    {
        $path = base_path("modules/$moduleName/Http/Requests/{$moduleName}Request.php");
        $contents = <<<PHP
        <?php

        namespace Modules\\$moduleName\\Http\\Requests;

        use Illuminate\\Foundation\\Http\\FormRequest;

        class {$moduleName}Request extends FormRequest
        {
            public function rules(): array
            {
                return [];
            }
        }
        PHP;
        File::put($path, $contents);
    }

    private function createModels($moduleName)
    {
        $path = base_path("modules/$moduleName/Models/{$moduleName}.php");
        $contents = <<<PHP
        <?php

        namespace Modules\\$moduleName\\Models;

        use Illuminate\\Database\\Eloquent\\Model;

        class {$moduleName} extends Model
        {
            protected \$table = '$moduleName';
        }
        PHP;
        File::put($path, $contents);
    }

    private function createViews($moduleName)
    {

        $content = <<<EOF
        @extends('main.index')
        <!-- Head -->
        @push('style')
            <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2.min.css') }}" />
            <link rel="stylesheet" href="{{ asset('assets/libs/select2/select2-bootstrap.css') }}" />
            <link rel="stylesheet" href="{{ asset('assets/libs/datatables/datatables.min.css') }}" />
        @endpush
        @push('javascript')
            <script src="{{ asset('assets/libs/select2/select2.min.js') }}"></script>
            <script src="{{ asset('assets/libs/select2/select2.init.js') }}"></script>
            <script src="{{ asset('assets/libs/datatables/datatables.min.js') }}"></script>
            <script src="{{ asset('assets/libs/datatables/datatable-bootstrap5.js') }}"></script>
        @endpush
        <!-- Mark Sidebar -->
        @section('{$moduleName}.show', 'show')
        @section('{$moduleName}.active', 'active')
        <!-- Mark Side Menu Sidebar -->
        @section('{$moduleName}.user-list.active', 'active')
        <!-- ------------------------------ -->
        @section('content')
         <!-- Page header -->
            <div class="page-header d-print-none">
                <div class="container-xl">
                    <div class="row g-2 align-items-center">
                        <div class="col">
                            <!-- Page pre-title -->
                            <div class="page-pretitle">
                                {$moduleName}
                            </div>
                            <h2 class="page-title">
                                {$moduleName}
                            </h2>
                        </div>
                        <div class="col-4 d-flex gap-3 align-items-center">
                            <div class="col-md-12">
                                // <label for="employeeSelect" class=" form-label">Search</label>
                                // <select id="employeeSelect" name="employee_find" class="form-select">
                                // </select>
                            </div>
                        </div>
                        <!-- Page title actions -->
                        <div class="col-auto ms-auto d-print-none">
                            <div class="row">
                                <div class="btn-list">
                                    <div class="col-12 d-flex">
                                        // <a href=""
                                        //     class="btn btn-primary py-1 mt-4 text-nowrap ms-auto">
                                        //     <i class="ti ti-plus me-2"></i> Search
                                        // </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12 mt-3">
                    <div class="card">
                        {{-- Body --}}
                    </div>
                </div>
            </div>
        @endsection
        EOF;
        $path = base_path("modules/$moduleName/Resources/views/index.blade.php");
        File::put($path, $content);
    }
    private function createAssets($moduleName)
    {
        File::put(base_path("modules/$moduleName/Resources/js/app.js"), '');
        File::put(base_path("modules/$moduleName/Resources/css/style.css"), '');
    }

    private function createRoutes($moduleName)
    {
        // Convert camelCase or PascalCase to kebab-case
        $routeName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $moduleName));
        $routeName = str_replace(['_', ' '], '-', $routeName); // Ensure underscores and spaces are replaced

        $webPath = base_path("modules/$moduleName/Routes/web/" . $routeName . ".php");
        $apiPath = base_path("modules/$moduleName/Routes/api/" . $routeName . ".php");


        $webContents = <<<PHP
        <?php

        use Illuminate\\Support\\Facades\\Route;
        use Modules\\$moduleName\\Http\\Controllers\\{$moduleName}Controller;

        Route::middleware(['web', 'auth'])->prefix('')->group(function () {
            Route::get('$routeName', [{$moduleName}Controller::class, 'index']);
        });
        PHP;

        $apiContents = <<<PHP
        <?php

        use Illuminate\\Support\\Facades\\Route;

        Route::prefix('api/$routeName')->group(function() {
            // API routes here
        });
        PHP;

        File::put($webPath, $webContents);
        File::put($apiPath, $apiContents);
    }

    private function registerRoutes($moduleName)
    {
        // Convert camelCase or PascalCase to kebab-case
        $routeName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $moduleName));
        $routeName = str_replace(['_', ' '], '-', $routeName); // Ensure underscores and spaces are replaced


        $routesFile = base_path('routes/web.php');
        $newCode = "require_once base_path('modules/" . $moduleName . "/Routes/web/" . $routeName . ".php');\n";

        if (file_exists($routesFile)) {
            $contents = file_get_contents($routesFile);
            if (!str_contains($contents, $newCode)) {
                file_put_contents($routesFile, $contents . "\n" . $newCode);
            }
        }
    }

    private function createRepositories($moduleName)
    {
        // Repository Interface (inside the Repositories folder)
        $interfacePath = base_path("modules/$moduleName/Repositories/{$moduleName}Interface.php");
        $interfaceContents = <<<PHP
        <?php

        namespace Modules\\$moduleName\\Repositories;

        interface {$moduleName}Interface
        {
            public function getAll();
        }
        PHP;
        File::put($interfacePath, $interfaceContents);

        // Repository Implementation
        $repositoryPath = base_path("modules/$moduleName/Repositories/{$moduleName}Repository.php");
        $repositoryContents = <<<PHP
        <?php

        namespace Modules\\$moduleName\\Repositories;

        class {$moduleName}Repository implements {$moduleName}Interface
        {
            public function getAll()
            {
                // Implement getAll method
            }
        }
        PHP;
        File::put($repositoryPath, $repositoryContents);
    }

    private function createProvider($moduleName)
    {
        $routeName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $moduleName));
        $routeName = str_replace(['_', ' '], '-', $routeName);

        $path = base_path("modules/$moduleName/Providers/{$moduleName}ServiceProvider.php");
        $contents = <<<PHP
        <?php

        namespace Modules\\$moduleName\\Providers;

        use Illuminate\Support\ServiceProvider;
        use Modules\\$moduleName\\Repositories\\{$moduleName}Repository;
        use Modules\\$moduleName\\Repositories\\{$moduleName}Interface;

        class {$moduleName}ServiceProvider extends ServiceProvider
        {
            public function boot()
            {
                \$this->loadRoutesFrom(base_path('modules/$moduleName/Routes/web/$routeName.php'));
                \$this->loadViewsFrom(base_path('modules/$moduleName/Resources/views'), '$moduleName');
            }

                public function register()
            {
                // ✅ Automatically bind all repositories
                \$repositoryPath = base_path("modules/$moduleName/Repositories");

                if (is_dir(\$repositoryPath)) {
                    foreach (scandir(\$repositoryPath) as \$file) {
                        if (preg_match('/^(.+)Interface\\.php\$/', \$file, \$matches)) {
                            \$interface = "Modules\\\\$moduleName\\\\Repositories\\\\{\$matches[1]}Interface";
                            \$repository = "Modules\\\\$moduleName\\\\Repositories\\\\{\$matches[1]}Repository";

                            if (interface_exists(\$interface) && class_exists(\$repository)) {
                                \$this->app->bind(\$interface, \$repository);
                            }
                        }
                    }
                }
            }
        }
        PHP;
        File::put($path, $contents);
    }

    private function registerProvider($moduleName)
    {
        $configPath = config_path('app.php');
        $providerLine = "Modules\\$moduleName\\Providers\\{$moduleName}ServiceProvider::class,";

        $contents = file_get_contents($configPath);
        if (!str_contains($contents, $providerLine)) {
            $contents = str_replace("'providers' => [", "'providers' => [\n        $providerLine", $contents);
            file_put_contents($configPath, $contents);
        }
    }
}
