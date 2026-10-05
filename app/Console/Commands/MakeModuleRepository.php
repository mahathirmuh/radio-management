<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeModuleRepository extends Command
{
    protected $signature = 'make:module-repository {module} {name}';
    protected $description = 'Create a repository inside a module';

    public function handle()
    {
        $module = $this->argument('module');
        $name = $this->argument('name');

        $modulePath = base_path("modules/$module");

        if (!File::exists($modulePath)) {
            $this->error("Module '$module' not found!");
            return;
        }

        $interfacePath = "$modulePath/Repositories/{$name}Interface.php";
        $repositoryPath = "$modulePath/Repositories/{$name}Repository.php";
        $namespace = "Modules\\$module\\Repositories";

        $interfaceContents = <<<PHP
        <?php

        namespace $namespace;

        interface {$name}Interface
        {
            public function getAll();
        }
        PHP;

        $repositoryContents = <<<PHP
        <?php

        namespace $namespace;

        class {$name}Repository implements {$name}Interface
        {
            public function getAll()
            {
                // Implement getAll method
            }
        }
        PHP;

        File::put($interfacePath, $interfaceContents);
        File::put($repositoryPath, $repositoryContents);

        $this->info("Repository '$name' and Interface created in module '$module'!");
    }
}
