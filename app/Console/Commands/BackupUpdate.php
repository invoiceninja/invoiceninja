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
use App\Models\Design;
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
    protected $signature = 'ninja:backup-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Shift backups from DB to storage';

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

        Backup::whereHas('activity')->whereRaw('html_backup IS NOT NULL')->cursor()->each(function ($backup) {
            if (strlen($backup->html_backup) > 1 && $backup->activity->invoice->exists()) {
                $client = $backup->activity->invoice->client;
                $backup->storeRemotely($backup->html_backup, $client);
            } elseif (strlen($backup->html_backup) > 1 && $backup->activity->quote->exists()) {
                $client = $backup->activity->quote->client;
                $backup->storeRemotely($backup->html_backup, $client);
            } elseif (strlen($backup->html_backup) > 1 && $backup->activity->credit->exists()) {
                $client = $backup->activity->credit->client;
                $backup->storeRemotely($backup->html_backup, $client);
            }
        });
    }
}
