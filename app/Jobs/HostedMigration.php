<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Libraries\Utils;
use App\Models\Account;
use App\Models\User;
use App\Services\Migration\CompleteService;
use App\Traits\GenerateMigrationResources;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Storage;
// use Unirest\Request;

class HostedMigration extends Job
{
    use GenerateMigrationResources;

    public $db;

    public $data;

    public $user;

    private $v4_secret;

    public $migration_token;

    private $forced;

    public function __construct(User $user, array $data, $db, $forced = false)
    {
        $this->user = $user;
        $this->data = $data;
        $this->db = $db;
        $this->forced = $forced;
        $this->v4_secret = config('ninja.ninja_hosted_secret');
    }

    /**
     * Execute the job.
     */
    public function handle()
    {

        config(['database.default' => $this->db]);

        //Create or get a token
        $this->getToken();

        $completeService = (new CompleteService($this->migration_token));

        $migrationData = $this->generateMigrationData($this->data);

        $completeService->data($migrationData)
        ->endpoint('https://v5-app1.invoicing.co')
        // ->endpoint('http://ninja.test:8000')
        ->start();

    }

    private function getToken()
    {
        $url = 'https://invoicing.co/api/v1/get_migration_account';
        // $url = 'http://ninja.test:8000/api/v1/get_migration_account';

        $headers = [
            'X-API-HOSTED-SECRET' => $this->v4_secret,
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/json',
        ];

        $body = [
            'first_name' => $this->user->first_name,
            'last_name' => $this->user->last_name,
            'email' => $this->user->email,
            'privacy_policy' => true,
            'terms_of_service' => true,
            'password' => '',
        ];

        $client =  new \GuzzleHttp\Client([
            'headers' =>  $headers,
        ]);

        $response = $client->post($url,[
            RequestOptions::JSON => $body, 
            RequestOptions::ALLOW_REDIRECTS => false
        ]);

        if($response->getStatusCode() == 401){
            info($response->getBody());

        } elseif ($response->getStatusCode() == 200) {

            $message_body = json_decode($response->getBody(), true);

            $this->migration_token = $message_body['token'];

        } else {
            info(json_decode($response->getBody()->getContents()));

        }


        // $body = \Unirest\Request\Body::json($body);

        // $response = Request::post($url, $headers, $body);

        // if (in_array($response->code, [200])) {
            
        //     $data = $response->body;
        //     info(print_r($data,1));
        //     $this->migration_token = $data->token; 

        // } else {
        //     info("getting token failed");
        //     info($response->raw_body);

        // }   

        return $this;
    }


    public function generateMigrationData(array $data): array
    {
        set_time_limit(0);

        $migrationData = [];

        foreach ($data['companies'] as $company) {
            $account = Account::where('account_key', $company['id'])->firstOrFail();

            $this->account = $account;

            if($this->forced){
                //forced migration - we need to set this v4 account as inactive.
                
                //set activate URL
                $account_email_settings = $this->account->account_email_settings;
                $account_email_settings->account_email_settings->forward_url_for_v5 = "https://invoiceninja-{$this->account->id}.invoicing.co";
                $account_email_settings->save();

                $this->account->subdomain = "invoiceninja-{$this->account->id}";
            }

            $date = date('Y-m-d');
            $accountKey = $this->account->account_key;

            $output = fopen('php://output', 'w') or Utils::fatalError();

            $fileName = "{$accountKey}-{$date}-invoiceninja";

            $localMigrationData['data'] = [
                'account' => $this->getAccount(),
                'company' => $this->getCompany(),
                'users' => $this->getUsers(),
                'tax_rates' => $this->getTaxRates(),
                'payment_terms' => $this->getPaymentTerms(),
                'clients' => $this->getClients(),
                'company_gateways' => $this->getCompanyGateways(),
                'client_gateway_tokens' => $this->getClientGatewayTokens(),
                'vendors' => $this->getVendors(),
                'projects' => $this->getProjects(),
                'products' => $this->getProducts(),
                'credits' => $this->getCreditsNotes(),
                'invoices' => $this->getInvoices(),
                'recurring_expenses' => $this->getRecurringExpenses(),
                'recurring_invoices' => $this->getRecurringInvoices(),
                'quotes' => $this->getQuotes(),
                'payments' => array_merge($this->getPayments(), $this->getCredits()),
                'documents' => $this->getDocuments(),
                'expense_categories' => $this->getExpenseCategories(),
                'task_statuses' => $this->getTaskStatuses(),
                'expenses' => $this->getExpenses(),
                'tasks' => $this->getTasks(),
                'documents' => $this->getDocuments(),
                'ninja_tokens' => $this->getNinjaToken(),
            ];

            $localMigrationData['force'] = array_key_exists('force', $company);

            Storage::makeDirectory('migrations');
            $file = Storage::path("migrations/{$fileName}.zip");

            //$file = storage_path("migrations/{$fileName}.zip");

            ksort($localMigrationData);

            $zip = new \ZipArchive();
            $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('migration.json', json_encode($localMigrationData, JSON_PRETTY_PRINT));
            $zip->close();

            $localMigrationData['file'] = $file;

            $migrationData[] = $localMigrationData;
        }

        return $migrationData;

    }
}