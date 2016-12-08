<?php

namespace App\Console\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;

class MakeClass extends GeneratorCommand
{
    use ModuleCommandTrait;

    protected $argumentName = 'name';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'ninja:make-class';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create class stub';

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the module.'],
            ['module', InputArgument::REQUIRED, 'The name of module will be used.'],
            ['class', InputArgument::REQUIRED, 'The name of the class.'],
            ['prefix', InputArgument::OPTIONAL, 'The prefix of the class.'],
        ];
    }

    public function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $path = str_replace('/', '\\', config('modules.paths.generator.' . $this->argument('class')));

        return (new Stub('/' . $this->argument('prefix') . $this->argument('class') . '.stub', [
            'NAMESPACE' => $this->getClassNamespace($module) . "\\" . $path,
            'LOWER_NAME' => $module->getLowerName(),
            'CLASS' => $this->getClass(),
            'STUDLY_NAME' => Str::studly($module->getLowerName()),
        ]))->render();
    }

    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());
        $seederPath = $this->laravel['modules']->config('paths.generator.'  . $this->argument('class'));

        return $path . $seederPath . '/' . $this->getFileName() . '.php';
    }

    /**
     * @return string
     */
    protected function getFileName()
    {
        return studly_case($this->argument('prefix')) . studly_case($this->argument('name')) . Str::studly($this->argument('class'));
    }

}
