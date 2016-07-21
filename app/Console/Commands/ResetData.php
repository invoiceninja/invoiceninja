<?php namespace App\Console\Commands;


use Utils;
use Illuminate\Console\Command;

/**
 * Class ResetData
 */
class ResetData extends Command
{

    /**
     * @var string
     */
    protected $name = 'ninja:reset-data';
    
    /**
     * @var string
     */
    protected $description = 'Reset data';

    public function fire()
    {
        $this->info(date('Y-m-d') . ' Running ResetData...');

        if (!Utils::isNinjaDev()) {
            return;
        }

        Artisan::call('migrate:reset');
        Artisan::call('migrate');
        Artisan::call('db:seed');
    }
}