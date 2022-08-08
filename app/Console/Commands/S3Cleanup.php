<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Utils\Ninja;
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

    protected $log = '';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove orphan folders/files';

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
        if (! Ninja::isHosted()) {
            return;
        }

        $c1 = Company::on('db-ninja-01')->pluck('company_key');
        $c2 = Company::on('db-ninja-02')->pluck('company_key');

        $merged = $c1->merge($c2)->toArray();

        $directories = Storage::disk(config('filesystems.default'))->directories();

        $this->LogMessage('Disk Cleanup');

        foreach ($directories as $dir) {
            if (! in_array($dir, $merged)) {
                $this->logMessage("Deleting $dir");

                /* Ensure we are not deleting the root folder */
                if (strlen($dir) > 1) {
                    Storage::disk(config('filesystems.default'))->deleteDirectory($dir);
                }
            }
        }

        $this->logMessage('exiting');
    }

    private function logMessage($str)
    {
        $str = date('Y-m-d h:i:s').' '.$str;
        $this->info($str);
        $this->log .= $str."\n";
    }
}
