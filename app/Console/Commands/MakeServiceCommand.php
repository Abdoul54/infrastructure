<?php


namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeServiceCommand extends Command
{
    protected $signature = 'make:service {name}';
    protected $description = 'Create a new Service class';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));  // e.g. Central/AuthService
        $path = app_path("Services/{$name}Service.php");

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);  // recursive true, creates nested folders
        }

        if (file_exists($path)) {
            $this->error("Service already exists!");
            return 1;
        }

        $stub = <<<EOT
<?php
namespace App\Services\\{$name};

class {$name}Service
{
    // Your service methods here
}

EOT;

        file_put_contents($path, $stub);
        $this->info("Service {$name}Service created successfully.");
        return 0;
    }
}
