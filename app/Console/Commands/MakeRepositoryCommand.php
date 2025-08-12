<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repository {name}';
    protected $description = 'Create a new Repository class and interface';

    public function handle()
    {
        $name = Str::studly($this->argument('name'));  // e.g. User or Central/User

        // Handle nested folders & class names
        $parts = explode('/', $name);
        $className = array_pop($parts);
        $namespace = 'App\Repositories' . (count($parts) ? '\\' . implode('\\', $parts) : '');
        $interfaceNamespace = $namespace . '\\Interfaces';

        // Paths
        $repoDir = app_path('Repositories' . (count($parts) ? '/' . implode('/', $parts) : ''));
        $interfaceDir = $repoDir . '/Interfaces';

        $repoPath = "{$repoDir}/{$className}Repository.php";
        $interfacePath = "{$interfaceDir}/{$className}RepositoryInterface.php";

        // Create directories if missing
        if (!is_dir($repoDir)) {
            mkdir($repoDir, 0755, true);
        }
        if (!is_dir($interfaceDir)) {
            mkdir($interfaceDir, 0755, true);
        }

        if (file_exists($repoPath) || file_exists($interfacePath)) {
            $this->error("Repository or Interface already exists!");
            return 1;
        }

        $repoStub = <<<EOT
<?php
namespace $namespace;

use $interfaceNamespace\\{$className}RepositoryInterface;

class {$className}Repository implements {$className}RepositoryInterface
{
    // Your repository methods here
}

EOT;

        $interfaceStub = <<<EOT
<?php
namespace $interfaceNamespace;

interface {$className}RepositoryInterface
{
    // Define your repository methods here
}

EOT;

        file_put_contents($repoPath, $repoStub);
        file_put_contents($interfacePath, $interfaceStub);

        $this->info("Repository {$className}Repository and interface created successfully.");
        return 0;
    }
}
