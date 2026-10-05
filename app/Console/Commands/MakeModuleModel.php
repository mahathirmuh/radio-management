<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeModuleModel extends Command
{
    protected $signature = 'make:module-model {module} {name}';
    protected $description = 'Create a model inside a module';

    public function handle()
    {
        $module = $this->argument('module');
        $name = $this->argument('name');

        $modulePath = base_path("modules/$module");

        if (!File::exists($modulePath)) {
            $this->error("Module '$module' not found!");
            return;
        }

        $path = "$modulePath/Models/{$name}.php";
        $namespace = "Modules\\$module\\Models";

        $contents = <<<PHP
        <?php

        namespace $namespace;

        use Illuminate\Database\Eloquent\Model;

        class $name extends Model
        {
            protected \$guarded = [];
        }
        PHP;

        File::put($path, $contents);
        $this->info("Model '$name' created in module '$module'!");
    }
}
