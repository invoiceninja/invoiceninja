<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

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

        exec("composer install");

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

    }
}
