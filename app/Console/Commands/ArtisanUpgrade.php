<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Composer\Console\Application;
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
        // Composer\Factory::getHomeDir() method 
        // needs COMPOSER_HOME environment variable set
        putenv('COMPOSER_HOME=' . __DIR__ . '/vendor/bin/composer');

        // call `composer install` command programmatically
        $input = new ArrayInput(array('command' => 'install'));
        $application = new Application();
        $application->setAutoExit(false); // prevent `$application->run` method from exitting the script
        $application->run($input);


        try {
        
            Artisan::call('migrate');
        
        }catch(Exception $e) {

            \Log::error("I wasn't able to migrate the data.");
        }

        try {
        
            Artisan::call('optimize');
        
        }catch(Exception $e) {

            \Log::error("I wasn't able to optimize.");
        }

        try {
        
            Artisan::call('queue:restart');
        
        }catch(Exception $e) {

            \Log::error("I wasn't able to restart the queue");
        }
    }
}
