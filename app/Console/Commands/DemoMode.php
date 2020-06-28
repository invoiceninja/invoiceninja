<?php

namespace App\Console\Commands;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Composer\Console\Application;

class DemoMode extends Command
{

    protected $name = 'ninja:demo-mode';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:demo-mode';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup demo mode';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        set_time_limit(0);

        $this->info("Migrating");
        Artisan::call('migrate:fresh --force');

        $this->info("Seeding");
        Artisan::call('db:seed --force');

        $this->info("Seeding Random Data");
        Artisan::call('db:seed --class=RandomDataSeeder --force');

    }
}
