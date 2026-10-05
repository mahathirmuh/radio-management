<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeModuleRequest extends Command
{
    protected $signature = 'make:module-request {module} {name}';
    protected $description = 'Create a request validation inside a module';

    public function handle()
    {
        $module = $this->argument('module');
        $name = $this->argument('name');

        $modulePath = base_path("modules/$module");

        if (!File::exists($modulePath)) {
            $this->error("Module '$module' not found!");
            return;
        }

        $path = "$modulePath/Http/Requests/{$name}.php";
        $namespace = "Modules\\$module\\Http\\Requests";

        $contents = <<<PHP
        <?php

        namespace $namespace;

        use Illuminate\Foundation\Http\FormRequest;

        class $name extends FormRequest
        {
            public function authorize()
            {
                return true;
            }

            public function rules()
            {
                return [
                    // Define rules here
                ];
            }
        }
        PHP;

        File::put($path, $contents);
        $this->info("Request '$name' created in module '$module'!");
    }
}
