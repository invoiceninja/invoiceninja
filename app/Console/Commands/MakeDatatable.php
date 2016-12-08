<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;

class MakeDatatable extends GeneratorCommand
{
    use ModuleCommandTrait;

    protected $argumentName = 'name';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'ninja:make-datatable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create datatable stub';

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the datatable.'],
            ['module', InputArgument::OPTIONAL, 'The name of module will be used.'],
        ];
    }

    public function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());

        return (new Stub('/datatable.stub', [
            'NAMESPACE' => $this->getClassNamespace($module) . "\\" . config('modules.paths.generator.datatables'),
            'LOWER_NAME' => $module->getLowerName(),
            'CLASS' => $this->getClass(),
        ]))->render();
    }

    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());
        $seederPath = $this->laravel['modules']->config('paths.generator.datatables');

        return $path . $seederPath . '/' . $this->getFileName() . '.php';
    }

    /**
     * @return string
     */
    protected function getFileName()
    {
        return studly_case($this->argument('name')) . 'Datatable';
    }

}
