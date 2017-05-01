<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Utils;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ResetData.
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

        if (! Utils::isNinjaDev()) {
            return;
        }

        if ($database = $this->option('database')) {
            config(['database.default' => $database]);
        }

        Artisan::call('migrate:reset');
        Artisan::call('migrate');
        Artisan::call('db:seed');
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['fix', null, InputOption::VALUE_OPTIONAL, 'Fix data', null],
            ['client_id', null, InputOption::VALUE_OPTIONAL, 'Client id', null],
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database', null],
        ];
    }
}
