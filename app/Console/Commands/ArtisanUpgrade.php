<?php

namespace App\Console\Commands;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;

class ArtisanUpgrade extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:post-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run basic upgrade commands';

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
        set_time_limit(0);

        try {
            Artisan::call('migrate');
        } catch (Exception $e) {
            \Log::error("I wasn't able to migrate the data.");
        }

        try {
            Artisan::call('optimize');
        } catch (Exception $e) {
            \Log::error("I wasn't able to optimize.");
        }

        $composer = Factory::create(new NullIO(), base_path('composer.json'), false);

        $output = Installer::create(new NullIO, $composer)
            ->setVerbose()
            ->setUpdate(true)
            ->run();
        
        \Log::error(print_r($output,1));



        // putenv('COMPOSER_HOME=' . __DIR__ . '/vendor/bin/composer');
        // $input = new ArrayInput(array('command' => 'update'));
        // $application = new Application();
        // $application->setAutoExit(true); // prevent `$application->run` method from exitting the script
        // $application->run($input);
    }
}
