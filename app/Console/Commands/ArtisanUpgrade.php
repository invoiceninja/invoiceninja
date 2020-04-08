<?php

namespace App\Console\Commands;

use Composer\Composer;
use Composer\Console\Application;
use Composer\IO\IOInterface;
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

        // putenv('COMPOSER_HOME=' . __DIR__ . '/vendor/bin/composer');
        // $input = new ArrayInput(array('command' => 'install'));
        // $application = new Application();
        // $application->setAutoExit(true); // prevent `$application->run` method from exitting the script
        // $application->run($input);

        $install = Installer::create(new IOInterface(), new Composer());

        $install
            ->setVerbose(true)
            ->setUpdate(true);        

        $status = $install->run();
    }
}
