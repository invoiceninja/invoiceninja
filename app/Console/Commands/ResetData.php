<?php namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ResetData extends Command {

  protected $name = 'ninja:reset-data';
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