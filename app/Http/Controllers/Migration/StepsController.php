<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Http\Requests\MigrationAuthRequest;
use App\Http\Requests\MigrationCompaniesRequest;
use App\Http\Requests\MigrationEndpointRequest;
use App\Http\Requests\MigrationForwardRequest;
use App\Http\Requests\MigrationTypeRequest;
use App\Jobs\HostedMigration;
use App\Libraries\Utils;
use App\Models\Account;
use App\Models\AccountGatewayToken;
use App\Models\Client;
use App\Services\Migration\AuthService;
use App\Services\Migration\CompanyService;
use App\Services\Migration\CompleteService;
use App\Traits\GenerateMigrationResources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Validator;
use GuzzleHttp\RequestOptions;

class StepsController extends BaseController
{
    use GenerateMigrationResources;

    public function __construct()
    {
        $this->middleware('migration');
    }

    private $access = [
        'auth' => [
            'steps' => ['MIGRATION_TYPE'],
            'redirect' => '/migration/start',
        ],
        'endpoint' => [
            'steps' => ['MIGRATION_TYPE'],
            'redirect' => '/migration/start',
        ],
        'companies' => [
            'steps' => ['MIGRATION_TYPE', 'MIGRATION_ACCOUNT_TOKEN'],
            'redirect' => '/migration/auth',
        ],
    ];

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function start()
    {
        if(Utils::isNinja()){
            
            session()->put('MIGRATION_ENDPOINT', 'https://v5-app1.invoicing.co');
        //    session()->put('MIGRATION_ENDPOINT', 'http://ninja.test:8000');
            session()->put('MIGRATION_ACCOUNT_TOKEN','');
            session()->put('MIGRAITON_API_SECRET', null);

            return $this->companies();

        }

        return view('migration.start');
    }

    public function import()
    {
        return view('migration.import');
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function download()
    {
        return view('migration.download');
    }

    public function handleType(MigrationTypeRequest $request)
    {
        session()->put('MIGRATION_TYPE', $request->option);

        if ($request->option == 0 || $request->option == '0') {

            return redirect(
                url('/migration/companies?hosted=true')
            );

        }

        return redirect(
            url('/migration/endpoint')
        );
    }

    public function forwardUrl(Request $request)
    {

        if(Utils::isNinjaProd())
            return $this->autoForwardUrl();
        
        $rules = [
            'url' => 'nullable|url',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        $account_settings = \Auth::user()->account->account_email_settings;

        if(strlen($request->input('url')) == 0) {
            $account_settings->is_disabled = false;
        }
        else {
            $account_settings->is_disabled = true;
        }

        $account_settings->forward_url_for_v5 = rtrim($request->input('url'),'/');
        $account_settings->save();

        return back();
    }

    public function disableForwarding()
    {
        $account = \Auth::user()->account;

        $account_settings = $account->account_email_settings;
        $account_settings->forward_url_for_v5 = '';
        $account_settings->is_disabled = false;
        $account_settings->save();

        return back();
    }

    private function autoForwardUrl()
    {

        $url = 'https://invoicing.co/api/v1/confirm_forwarding';
        // $url = 'http://devhosted.test:8000/api/v1/confirm_forwarding';

        $headers = [
            'X-API-HOSTED-SECRET' => config('ninja.ninja_hosted_secret'),
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/json',
        ];

        $account = \Auth::user()->account;
        $gateway_reference = '';

        $ninja_client = Client::where('public_id', $account->id)->first();

        if($ninja_client){
            $agt = AccountGatewayToken::where('client_id', $ninja_client->id)->first();

            if($agt)
                $gateway_reference = $agt->token;
        }

        $body = [
            'account_key' => $account->account_key,
            'email' => $account->getPrimaryUser()->email,
            'plan' => $account->company->plan,
            'plan_term' =>$account->company->plan_term,
            'plan_started' =>$account->company->plan_started,
            'plan_paid' =>$account->company->plan_paid,
            'plan_expires' =>$account->company->plan_expires,
            'trial_started' =>$account->company->trial_started,
            'trial_plan' =>$account->company->trial_plan,
            'plan_price' =>$account->company->plan_price,
            'num_users' =>$account->company->num_users,
            'gateway_reference' => $gateway_reference,
        ];

        $client =  new \GuzzleHttp\Client([
            'headers' =>  $headers,
        ]);

        $response = $client->post($url,[
            RequestOptions::JSON => $body, 
            RequestOptions::ALLOW_REDIRECTS => false
        ]);

        if($response->getStatusCode() == 401){
            info("autoForwardUrl");
            info($response->getBody());

        } elseif ($response->getStatusCode() == 200) {

            $message_body = json_decode($response->getBody(), true);

            $forwarding_url = $message_body['forward_url'];

                $account_settings = $account->account_email_settings;

                if(strlen($forwarding_url) == 0) {
                    $account_settings->is_disabled = false;
                }
                else {
                    $account_settings->is_disabled = true;
                }

                $account_settings->forward_url_for_v5 = rtrim($forwarding_url,'/');
                $account_settings->save();

            $billing_transferred = $message_body['billing_transferred'];

            if($billing_transferred == 'true'){

                $company = $account->company;
                $company->plan = null;
                $company->plan_expires = null;
                $company->save();
            }


        } else {
            info("failed to autoforward");
            info(json_decode($response->getBody()->getContents()));
            // dd('else');
        }

        return back();

    }

    public function endpoint()
    {

        if ($this->shouldGoBack('endpoint')) {
            return redirect(
                url($this->access['endpoint']['redirect'])
            );
        }

        return view('migration.endpoint');
    }

    public function handleEndpoint(MigrationEndpointRequest $request)
    {
        if ($this->shouldGoBack('endpoint')) {
            return redirect(
                url($this->access['endpoint']['redirect'])
            );
        }

        session()->put('MIGRATION_ENDPOINT', rtrim($request->endpoint,'/'));

        return redirect(
            url('/migration/auth')
        );
    }

    public function auth()
    {
        if ($this->shouldGoBack('auth')) {
            return redirect(
                url($this->access['auth']['redirect'])
            );
        }

        return view('migration.auth');
    }

    public function handleAuth(MigrationAuthRequest $request)
    {
        if ($this->shouldGoBack('auth')) {
            return redirect(
                url($this->access['auth']['redirect'])
            );
        }

        if (auth()->user()->email !== $request->email) {
            return back()->with('responseErrors', [trans('texts.cross_migration_message')]);
        }

        $authentication = (new AuthService($request->email, $request->password, $request->has('api_secret') ? $request->api_secret : null))
            ->endpoint(session('MIGRATION_ENDPOINT'))
            ->start();

        if ($authentication->isSuccessful()) {
            session()->put('MIGRATION_ACCOUNT_TOKEN', $authentication->getAccountToken());
            session()->put('MIGRAITON_API_SECRET', $authentication->getApiSecret());

            return redirect(
                url('/migration/companies')
            );
        }

        return back()->with('responseErrors', $authentication->getErrors());
    }

    public function companies()
    {
        if ($this->shouldGoBack('companies')) {
            return redirect(
                url($this->access['companies']['redirect'])
            );
        }

        $companyService = (new CompanyService())
            ->start();

        if ($companyService->isSuccessful()) {
            return view('migration.companies', ['companies' => $companyService->getCompanies()]);
        }

        return response()->json([
            'message' => 'Oops, looks like something failed. Please try again.'
        ], 500);
    }

    public function handleCompanies(MigrationCompaniesRequest $request)
    {
        if ($this->shouldGoBack('companies')) {
            return redirect(
                url($this->access['companies']['redirect'])
            );
        }
        $bool = true;

        if(Utils::isNinja())
        {

            $this->dispatch(new HostedMigration(auth()->user(), $request->all(), config('database.default')));
            
            return view('migration.completed');
   
        }

        $completeService = (new CompleteService(session('MIGRATION_ACCOUNT_TOKEN')));

        try {
            $migrationData = $this->generateMigrationData($request->all());
            
                $completeService->data($migrationData)
                ->endpoint(session('MIGRATION_ENDPOINT'))
                ->start();
        }
        catch(\Exception $e){
            info($e->getMessage());
            return view('migration.completed', ['customMessage' => $e->getMessage()]);
        }
     
        
            if ($completeService->isSuccessful()) {
                return view('migration.completed');
            }

            return view('migration.completed', ['customMessage' => $completeService->getErrors()[0]]);
        
    }

    public function completed()
    {
        return view('migration.completed');
    }

    /**
     * ==================================
     * Rest of functions that are used as 'actions', not controller methods.
     * ==================================
     */

    public function shouldGoBack(string $step)
    {
        $redirect = true;

        foreach ($this->access[$step]['steps'] as $step) {
            if (session()->has($step)) {
                $redirect = false;
            } else {
                $redirect = true;
            }
        }

        return $redirect;
    }

    /**
     * Handle data downloading for the migration.
     *
     * @return string
     */
    public function generateMigrationData(array $data): array
    {
        set_time_limit(0);

        $migrationData = [];

        foreach ($data['companies'] as $company) {
            $account = Account::where('account_key', $company['id'])->firstOrFail();

            $this->account = $account;

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
                'payments' => $this->getPayments(),
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

            ksort($localMigrationData);

            $zip = new \ZipArchive();
            $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('migration.json', json_encode($localMigrationData, JSON_PRETTY_PRINT));
            $zip->close();

            $localMigrationData['file'] = $file;

            $migrationData[] = $localMigrationData;
        }

        return $migrationData;

        // header('Content-Type: application/zip');
        // header('Content-Length: ' . filesize($file));
        // header("Content-Disposition: attachment; filename={$fileName}.zip");
    }
}
