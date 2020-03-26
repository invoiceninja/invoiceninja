<?php

namespace App\Console\Commands;

use Artisan;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

class MakeModule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:make-module {name : Module name} {fields? : Model fields} {--migrate : Run module migrations} {--p|--plain : Generate only base module scaffold}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Module CRUD';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $fields = $this->argument('fields');
        $migrate = $this->option('migrate');
        $plain = $this->option('plain');
        $lower = strtolower($name);

        // convert 'name:string,description:text' to 'name,description'
        $fillable = explode(',', $fields);
        $fillable = array_map(function ($item) {
            return explode(':', $item)[0];
        }, $fillable);
        $fillable = implode(',', $fillable);

        ProgressBar::setFormatDefinition('custom', '%current%/%max% %elapsed:6s% [%bar%] %percent:3s%% %message%');
        $progressBar = $this->output->createProgressBar($plain ? 2 : ($migrate ? 15 : 14));
        $progressBar->setFormat('custom');

        $this->info("Creating module: {$name}...");
        $progressBar->setMessage("Starting module creation...");
        Artisan::call('module:make', ['name' => [$name]]);
        $progressBar->advance();

        if (! $plain) {
            $progressBar->setMessage("Creating migrations...");
            Artisan::call('module:make-migration', ['name' => "create_{$lower}_table", '--fields' => $fields, 'module' => $name]);
            $progressBar->advance();

            $progressBar->setMessage("Creating models...");
            Artisan::call('module:make-model', ['model' => $name, 'module' => $name, '--fillable' => $fillable]);
            $progressBar->advance();

            $progressBar->setMessage("Creating views...");
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'views', '--fields' => $fields, '--filename' => 'edit.blade']);
            $progressBar->advance();

            $progressBar->setMessage("Creating datatables...");
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'datatable', '--fields' => $fields]);
            $progressBar->advance();

            $progressBar->setMessage("Creating repositories...");
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'repository', '--fields' => $fields]);
            $progressBar->advance();

            $progressBar->setMessage("Creating presenters...");
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'presenter']);
            $progressBar->advance();

            $progressBar->setMessage("Creating requests...");
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'request']);
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'request', 'prefix' => 'create']);
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'request', 'prefix' => 'update']);
            $progressBar->advance();

            $progressBar->setMessage("Creating api-controllers...");
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'api-controller']);
            $progressBar->advance();

            $progressBar->setMessage("Creating transformers...");
            Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'transformer', '--fields' => $fields]);
            $progressBar->advance();

            // if the migrate flag was specified, run the migrations
            if ($migrate) {
                $progressBar->setMessage("Running migrations...");
                Artisan::call('module:migrate', ['module' => $name]);
                $progressBar->advance();
            }
        }

        $progressBar->setMessage("Creating policies...");
        Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'policy']);
        $progressBar->advance();

        $progressBar->setMessage("Creating auth-providers...");
        Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'auth-provider']);
        $progressBar->advance();

        $progressBar->setMessage("Creating translations...");
        Artisan::call('ninja:make-class', ['name' => $name, 'module' => $name, 'class' => 'lang', '--filename' => 'texts']);
        $progressBar->advance();

        $progressBar->setMessage("Dumping module auto-load...");
        Artisan::call('module:dump');
        $progressBar->finish();
        $progressBar->clear();

        $this->info('Done');

        if (!$migrate && !$plain) {
            $this->info("==> Migrations were not run because the --migrate flag was not specified.");
            $this->info("==> Use the following command to run the migrations:\nphp artisan module:migrate $name");
        }
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the module.'],
            ['fields', InputArgument::OPTIONAL, 'The fields of the module.'],
        ];
    }

    protected function getOptions()
    {
        return [
            ['migrate', null, InputOption::VALUE_NONE, 'Run module migrations.', null],
            ['plain', 'p', InputOption::VALUE_NONE, 'Generate only base module scaffold.', null],
        ];
    }
}
