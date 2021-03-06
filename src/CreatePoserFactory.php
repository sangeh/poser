<?php

namespace Lukeraymonddowning\Poser;

use ReflectionException;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreatePoserFactory extends GeneratorCommand
{
    protected $signature = 'make:poser {name? : The name of the Poser Factory}
                                       {--m|model= : The model that this factory is linked too}
                                       {--f|factory : Also create the Laravel database factory}';

    protected $description = 'Creates a Poser Model Factory with the given name';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub($stubVariant = null)
    {
        return __DIR__ . '/stubs/FactoryStub' . ($stubVariant ? '.' . $stubVariant : '') . '.txt';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');

        if ($name) {
            $this->createFactory($name);

            return;
        }

        $this->createAllFactories();
    }

    protected function createFactory($factoryName, $className = null)
    {
        $this->info("Creating Poser Factory called " . $factoryName);

        $expectedModelNameSpace = '\\' . modelsNamespace() . Str::beforeLast($factoryName, 'Factory');

        // TODO - Decide if this actually should be caught
        if (($optionModel = $this->option('model')) !== null) {
            try {
                $modelReflection = new \ReflectionClass($optionModel);
                $linkedModelNamespace = '\\' . $modelReflection->getName();
            } catch (ReflectionException $e) {
                $linkedModelNamespace = $expectedModelNameSpace;
            }
        } else {
            $linkedModelNamespace = $expectedModelNameSpace;
        }

        // TODO - or just choose this instead
        // $modelReflection = new \ReflectionClass($this->option('model') ?? $expectedModelNameSpace);
        // $linkedModelNamespace = '\\' . $modelReflection->getName();

        $destinationDirectory = base_path(str_replace("\\", "/", factoriesNamespace()));

        File::ensureDirectoryExists($destinationDirectory);

        $destination = $destinationDirectory . $factoryName . ".php";

        if (File::exists($destination)) {
            $this->error("There is already a Factory called " . $factoryName . " at " . $destinationDirectory);

            return;
        }

        $stubVariant = null;
        if ($expectedModelNameSpace !== $linkedModelNamespace) {
            $stubVariant = 'model';
        }

        File::copy($this->getStub($stubVariant), $destination);

        $value = File::get($destination);

        $namespace = str_replace('/', '\\', factoriesNamespace());
        if (Str::endsWith($namespace, '\\')) {
            $namespace = Str::beforeLast($namespace, '\\');
        }

        $valueFormatted = str_replace(
            [
                "{{ Namespace }}",
                "{{ ClassName }}",
                "{{ ModelNamespace }}",
            ],
            [
                $namespace,
                $factoryName,
                $linkedModelNamespace,
            ],
            $value
        );

        File::put($destination, $valueFormatted);

        $this->info($factoryName . " successfully created at " . $destination);
        $this->line("");
        $this->line("Remember, you should have a corresponding model, database factory and migration");

        if ($this->option('factory')) {
            $this->line("");
            $this->line("Creating database factory");

            $this->createDatabaseFactory($linkedModelNamespace);
        }

        $this->line("");
        $this->info("Please consider starring the repo at https://github.com/lukeraymonddowning/poser");
    }

    protected function createAllFactories()
    {
        $this->info("Creating Factories from all Models...");
        collect(File::files(str_replace('\\', '/', modelsNamespace())))
            ->filter(
                function ($fileInfo) {
                    return class_exists(modelsNamespace() . File::name($fileInfo));
                }
            )->map(
                function ($fileInfo) {
                    return modelsNamespace() . File::name($fileInfo);
                }
            )->filter(
                function ($className) {
                    return is_subclass_of($className, Model::class);
                }
            )->map(
                function ($className) {
                    return Str::substr($className, Str::length(modelsNamespace()));
                }
            )->map(
                function ($modelType) {
                    return $modelType . "Factory";
                }
            )->filter(
                function ($factoryName) {
                    return !class_exists(factoriesNamespace() . $factoryName);
                }
            )->each(
                function ($factoryName) {
                    $this->createFactory($factoryName);
                }
            );
    }

    /**
     * Create a database factory for the model.
     *
     * @param string $modelNamespace
     *
     * @return void
     */
    protected function createDatabaseFactory($modelNamespace)
    {
        $factory = Str::studly(class_basename($modelNamespace));

        $this->call(
            'make:factory',
            [
                'name' => "{$factory}Factory",
                '--model' => $this->qualifyClass($factory),
            ]
        );
    }
}
