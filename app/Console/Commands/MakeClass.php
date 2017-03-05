<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Support\Stub;

use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

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

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['fields', null, InputOption::VALUE_OPTIONAL, 'The model attributes.', null],
            ['filename', null, InputOption::VALUE_OPTIONAL, 'The class filename.', null],
        ];
    }

    public function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $path = str_replace('/', '\\', config('modules.paths.generator.' . $this->argument('class')));

        return (new Stub('/' . $this->argument('prefix') . $this->argument('class') . '.stub', [
            'NAMESPACE' => $this->getClassNamespace($module) . '\\' . $path,
            'LOWER_NAME' => $module->getLowerName(),
            'CLASS' => $this->getClass(),
            'STUDLY_NAME' => Str::studly($module->getLowerName()),
            'DATATABLE_COLUMNS' => $this->getColumns(),
            'FORM_FIELDS' => $this->getFormFields(),
            'DATABASE_FIELDS' => $this->getDatabaseFields($module),
            'TRANSFORMER_FIELDS' => $this->getTransformerFields($module),
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
        if ($this->option('filename')) {
            return $this->option('filename');
        }

        return studly_case($this->argument('prefix')) . studly_case($this->argument('name')) . Str::studly($this->argument('class'));
    }

    protected function getColumns()
    {
        $fields = $this->option('fields');
        $fields = explode(',', $fields);
        $str = '';

        foreach ($fields as $field) {
            if (! $field) {
                continue;
            }
            $field = explode(':', $field)[0];
            $str .= '[
                \''. $field . '\',
                function ($model) {
                    return $model->' . $field . ';
                }
            ],';
        }

        return $str;
    }

    protected function getFormFields()
    {
        $fields = $this->option('fields');
        $fields = explode(',', $fields);
        $str = '';

        foreach ($fields as $field) {
            if (! $field) {
                continue;
            }
            $parts = explode(':', $field);
            $field = $parts[0];
            $type = $parts[1];

            if ($type == 'text') {
                $str .= "{!! Former::textarea('" . $field . "') !!}\n";
            } else {
                $str .= "{!! Former::text('" . $field . "') !!}\n";
            }
        }

        return $str;
    }

    protected function getDatabaseFields($module)
    {
        $fields = $this->option('fields');
        $fields = explode(',', $fields);
        $str = '';

        foreach ($fields as $field) {
            if (! $field) {
                continue;
            }
            $field = explode(':', $field)[0];
            $str .= "'" . $module->getLowerName() . ".{$field}', ";
        }

        return $str;
    }

    protected function getTransformerFields($module)
    {
        $fields = $this->option('fields');
        $fields = explode(',', $fields);
        $str = '';

        foreach ($fields as $field) {
            if (! $field) {
                continue;
            }
            $field = explode(':', $field)[0];
            $str .= "'{$field}' => $" . $module->getLowerName() . "->$field,\n            ";
        }

        return rtrim($str);
    }
}
