<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Console\Commands;

use App\Libraries\MultiDB;
use App\Models\Backup;
use App\Models\Company;
use App\Models\Design;
use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use stdClass;

class BackupUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:backup-files {--disk=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Shift files between object storage locations';

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
        //always return state to first DB

        $current_db = config('database.default');

        if (! config('ninja.db.multi_db_enabled')) {
            $this->handleOnDb();
        } else {

            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->handleOnDb();
            }

            MultiDB::setDB($current_db);
        }
    }

    private function handleOnDb()
    {
        set_time_limit(0);

        //logos
        Company::cursor()
               ->each(function ($company){

                  $company_logo = $company->present()->logo();

                  if($company_logo == 'https://invoicing.co/images/new_logo.png')
                    return;

                  $logo = @file_get_contents($company_logo);

                    if($logo){

                        $path = str_replace("https://objects.invoicing.co/", "", $company->present()->logo());
                        $path = str_replace("https://v5-at-backup.us-southeast-1.linodeobjects.com/", "", $path);

                        Storage::disk($this->option('disk'))->put($path, $logo);
                    }

               });


        //documents
        Document::cursor()
                ->each(function ($document){

                    $doc_bin = $document->getFile();

                    if($doc_bin)
                        Storage::disk($this->option('disk'))->put($document->url, $doc_bin);

                });


       //backups
        Backup::cursor()
                ->each(function ($backup){

                  $backup_bin = Storage::disk('s3')->get($backup->filename);

                    if($backup_bin)
                        Storage::disk($this->option('disk'))->put($backup->filename, $backup_bin);

                });


        
    }
}
