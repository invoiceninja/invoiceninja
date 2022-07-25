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

use App\Http\ValidationRules\ValidClientGroupSettingsRule;
use App\Libraries\MultiDB;
use App\Models\Backup;
use App\Models\Client;
use App\Models\Company;
use App\Models\Design;
use App\Utils\Traits\ClientGroupSettingsSaver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use stdClass;

class TypeCheck extends Command
{
    use ClientGroupSettingsSaver;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:type-check {--all=} {--client_id=} {--company_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Settings Types';

    protected $log = '';

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

        if ($this->option('all')) {
            if (! config('ninja.db.multi_db_enabled')) {
                $this->checkAll();
            } else {
                foreach (MultiDB::$dbs as $db) {
                    MultiDB::setDB($db);

                    $this->checkAll();
                }

                MultiDB::setDB($current_db);
            }
        }

        if ($this->option('client_id')) {
            $client = MultiDB::findAndSetDbByClientId($this->option('client_id'));

            if ($client) {
                $this->checkClient($client);
            } else {
                $this->logMessage(date('Y-m-d h:i:s').' Could not find this client');
            }
        }

        if ($this->option('company_id')) {
            $company = MultiDB::findAndSetDbByCompanyId($this->option('company_id'));

            if ($company) {
                $this->checkCompany($company);
            } else {
                $this->logMessage(date('Y-m-d h:i:s').' Could not find this company');
            }
        }
    }

    private function checkClient($client)
    {
        $this->logMessage(date('Y-m-d h:i:s').' Checking Client => '.$client->present()->name().' '.$client->id);

        $entity_settings = $this->checkSettingType($client->settings);
        $entity_settings->md5 = md5(time());
        $client->settings = $entity_settings;
        $client->save();
    }

    private function checkCompany($company)
    {
        $this->logMessage(date('Y-m-d h:i:s').' Checking Company => '.$company->present()->name().' '.$company->id);

        $company->saveSettings((array) $company->settings, $company);
    }

    private function checkAll()
    {
        $this->logMessage(date('Y-m-d h:i:s').' Checking all clients and companies.');

        Client::withTrashed()->cursor()->each(function ($client) {
            $this->logMessage("Checking client {$client->id}");
            $entity_settings = $this->checkSettingType($client->settings);
            $entity_settings->md5 = md5(time());
            $client->settings = $entity_settings;
            $client->save();
        });

        Company::cursor()->each(function ($company) {
            $this->logMessage("Checking company {$company->id}");
            $company->saveSettings($company->settings, $company);
        });
    }

    private function logMessage($str)
    {
        $str = date('Y-m-d h:i:s').' '.$str;
        $this->info($str);
        $this->log .= $str."\n";
    }
}
