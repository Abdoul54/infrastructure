<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Filesystem\Filesystem;

class MakeRepositoryCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:repository';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository class with interface';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Repository';

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        // Ensure the class name ends with 'Repository'
        if (!Str::endsWith($name, 'Repository')) {
            $name .= 'Repository';
        }

        return parent::qualifyClass($name);
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     */
    public function handle()
    {
        $name = $this->qualifyClass($this->getNameInput());

        // Generate the repository interface first
        if (!$this->createInterface($name)) {
            return false;
        }

        // Generate the repository implementation
        if (parent::handle() === false) {
            return false;
        }

        // Optionally register in service provider
        if ($this->option('register')) {
            $this->registerInServiceProvider($name);
        }

        $this->info('Repository created successfully.');

        if (!$this->option('register')) {
            $this->comment('Don\'t forget to bind the interface to implementation in a service provider:');
            $this->line('$this->app->bind(');
            $this->line('    \\' . $this->getInterfaceNamespace($name) . '::class,');
            $this->line('    \\' . $name . '::class');
            $this->line(');');
        }

        return true;
    }

    /**
     * Create the interface for the repository.
     *
     * @param string $name
     * @return bool
     */
    protected function createInterface($name)
    {
        $interfaceName = $this->getInterfaceNamespace($name);
        $path = $this->getInterfacePath($interfaceName);

        if ((!$this->hasOption('force') || !$this->option('force')) && $this->alreadyExists($interfaceName)) {
            $this->error('Interface already exists!');
            return false;
        }

        $this->makeDirectory($path);

        $stub = $this->buildInterface($interfaceName, $name);

        $this->files->put($path, $stub);
        $this->info('Interface created successfully.');

        return true;
    }

    /**
     * Build the interface with the given name.
     *
     * @param string $interfaceName
     * @param string $repositoryName
     * @return string
     */
    protected function buildInterface($interfaceName, $repositoryName)
    {
        $stub = $this->files->get($this->getInterfaceStub());

        return $this->replaceNamespace($stub, $interfaceName)
            ->replaceClass($stub, $interfaceName);
    }

    /**
     * Get the interface namespace.
     *
     * @param string $name
     * @return string
     */
    protected function getInterfaceNamespace($name)
    {
        $name = str_replace('\\', '/', $name);
        $parts = explode('/', $name);
        $className = array_pop($parts);

        // Remove 'Repository' suffix if present to avoid 'RepositoryRepositoryInterface'
        $className = Str::replaceLast('Repository', '', $className);

        $parts[] = 'Contracts';
        $parts[] = $className . 'RepositoryInterface';

        return str_replace('/', '\\', implode('/', $parts));
    }

    /**
     * Get the interface path.
     *
     * @param string $name
     * @return string
     */
    protected function getInterfacePath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);
        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '.php';
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        if ($this->option('model')) {
            return $this->resolveStubPath('/stubs/repository.model.stub');
        }

        if ($this->option('resource')) {
            return $this->resolveStubPath('/stubs/repository.resource.stub');
        }

        return $this->resolveStubPath('/stubs/repository.stub');
    }

    /**
     * Get the interface stub file.
     *
     * @return string
     */
    protected function getInterfaceStub()
    {
        if ($this->option('model')) {
            return $this->resolveStubPath('/stubs/repository.interface.model.stub');
        }

        if ($this->option('resource')) {
            return $this->resolveStubPath('/stubs/repository.interface.resource.stub');
        }

        return $this->resolveStubPath('/stubs/repository.interface.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param string $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . $stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Repositories';
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $interfaceNamespace = $this->getInterfaceNamespace($name);
        $interfaceClass = class_basename($interfaceNamespace);

        $stub = str_replace(
            ['{{ interfaceNamespace }}', '{{interfaceNamespace}}', '{{ interface }}', '{{interface}}'],
            [$interfaceNamespace, $interfaceNamespace, $interfaceClass, $interfaceClass],
            $stub
        );

        if ($this->option('model')) {
            $stub = $this->replaceModel($stub, $this->option('model'));
        }

        return $stub;
    }

    /**
     * Replace the model for the given stub.
     *
     * @param string $stub
     * @param string $model
     * @return string
     */
    protected function replaceModel($stub, $model)
    {
        $modelClass = $this->parseModel($model);

        $replace = [
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}' => $modelClass,
            '{{ model }}' => class_basename($modelClass),
            '{{model}}' => class_basename($modelClass),
            '{{ modelVariable }}' => lcfirst(class_basename($modelClass)),
            '{{modelVariable}}' => lcfirst(class_basename($modelClass)),
        ];

        return str_replace(
            array_keys($replace),
            array_values($replace),
            $stub
        );
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param string $model
     * @return string
     */
    protected function parseModel($model)
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new \InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }

    /**
     * Register the repository binding in a service provider.
     *
     * @param string $name
     * @return void
     */
    protected function registerInServiceProvider($name)
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        if (!file_exists($providerPath)) {
            $this->call('make:provider', ['name' => 'RepositoryServiceProvider']);
            $this->info('Created RepositoryServiceProvider.');
        }

        $interfaceNamespace = $this->getInterfaceNamespace($name);
        $content = file_get_contents($providerPath);

        $binding = "\$this->app->bind(\n" .
            "            \\{$interfaceNamespace}::class,\n" .
            "            \\{$name}::class\n" .
            "        );";

        // Add binding in register method
        if (strpos($content, $binding) === false) {
            $pattern = '/(public function register\(\)[^{]*{)/';
            $replacement = "$1\n        {$binding}\n";
            $content = preg_replace($pattern, $replacement, $content, 1);

            file_put_contents($providerPath, $content);
            $this->info('Repository binding added to RepositoryServiceProvider.');
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the repository'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Generate a repository for the given model'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Generate a resource repository with CRUD methods'],
            ['register', null, InputOption::VALUE_NONE, 'Automatically register the binding in RepositoryServiceProvider'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the repository already exists'],
        ];
    }
}
