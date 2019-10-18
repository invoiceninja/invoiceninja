<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nwidart\Modules\Commands\GeneratorCommand;
use Nwidart\Modules\Support\Stub;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeModuleSettings extends GeneratorCommand
{
    use ModuleCommandTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'ninja:make-module-settings {name : Module name} {--route : Add routes }';

    protected $name = 'ninja:make-module-settings';
    protected $argumentName = 'module';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create module settings';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function getTemplateContents()
    {
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());
        $path = str_replace('/', '\\', config('modules.paths.generator.module-settings-view'));

        return (new Stub('/module-settings-view.stub', [
            'MODULE_NAME' => $module->getName(),
            'LOWER_NAME' => $module->getLowerName(),
            'SHOW_ROUTES' => $this->option('route') ? true : false
        ]))->render();
    }

    public function handle() {
        $this->info('Creating settings view template for ' . $this->getModuleName());
        $module = $this->laravel['modules']->findOrFail($this->getModuleName());

        parent::handle();

        // add default routes if option specified
        $route =  $this->option('route');

        if ($route) {
            file_put_contents(
                $this->getModuleRoutesFilePath(),
                (new Stub('/module-settings-routes.stub', [
                    'MODULE_NAME' => $module->getName(),
                    'LOWER_NAME' => $module->getLowerName(),
                ]))->render(),
                FILE_APPEND
            );
            $this->info('Added routes to module routes.php.');
        }
    }

    protected function getModuleRoutesFilePath() {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());
        $seederPath = $this->laravel['modules']->config('paths.generator.module-settings-routes');

        return $path . $seederPath . '/routes.php';
    }

    public function getDestinationFilePath()
    {
        $path = $this->laravel['modules']->getModulePath($this->getModuleName());
        $seederPath = $this->laravel['modules']->config('paths.generator.module-settings-view');

        return $path . $seederPath . '/' . $this->getFileName();
    }

    protected function getArguments()
    {
        return [
            ['module', InputArgument::REQUIRED, 'The name of the module.']
        ];
    }

    protected function getOptions()
    {
        return [
            ['route', null, InputOption::VALUE_NONE, 'Add default routes.', null]
        ];
    }

    /**
     * @return string
     */
    protected function getFileName()
    {
        return 'settings.blade.php';
    }
}
