<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class S3Cleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:s3-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove orphan folders';

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
     * @return int
     */
    public function handle()
    {

        $c1 = Company::on('db-ninja-01')->pluck('company_key');
        $c2 = Company::on('db-ninja-02')->pluck('company_key');

        $merged = $c1->merge($c2);

        $c3 = Storage::disk(config('filesystems.default'))->directories();

        $diff = $merged->diff($c3);

        $this->LogMessage("Disk Cleanup");

        $this->logMessage("Folders to delete = ". $c1->count());

        $diff->each(function ($dir){
        
            $this->logMessage("Deleting $dir");

            Storage::deleteDirectory($dir);

        });

        $this->logMessage("exiting");

    }

    private function logMessage($str)
    {
        $str = date('Y-m-d h:i:s').' '.$str;
        $this->info($str);
        $this->log .= $str."\n";
    }
}
