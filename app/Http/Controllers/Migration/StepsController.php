<?php

namespace App\Http\Controllers\Migration;

use App\Models\User;
use App\Models\Credit;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxRate;
use App\Libraries\Utils;
use App\Models\Document;
use App\Models\PaymentMethod;
use App\Models\AccountGateway;
use App\Models\AccountGatewayToken;
use App\Traits\GenerateMigrationResources;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use App\Models\AccountGatewaySettings;
use App\Services\Migration\AuthService;
use App\Http\Controllers\BaseController;
use App\Services\Migration\CompanyService;
use App\Http\Requests\MigrationAuthRequest;
use App\Http\Requests\MigrationTypeRequest;
use App\Services\Migration\CompleteService;
use App\Http\Requests\MigrationEndpointRequest;
use App\Http\Requests\MigrationCompaniesRequest;

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

        if ($request->option == 0)
            return redirect('/migration/auth');

        return redirect('/migration/endpoint');
    }

    public function endpoint()
    {
        if ($this->shouldGoBack('endpoint'))
            return redirect($this->access['endpoint']['redirect']);

        return view('migration.endpoint');
    }

    public function handleEndpoint(MigrationEndpointRequest $request)
    {
        if ($this->shouldGoBack('endpoint'))
            return redirect($this->access['endpoint']['redirect']);

        session()->put('MIGRATION_ENDPOINT', $request->endpoint);

        return redirect('/migration/auth');
    }

    public function auth()
    {
        if ($this->shouldGoBack('auth'))
            return redirect($this->access['auth']['redirect']);

        return view('migration.auth');
    }

    public function handleAuth(MigrationAuthRequest $request)
    {
        if ($this->shouldGoBack('auth')) {
            return redirect($this->access['auth']['redirect']);
        }

        if (auth()->user()->email !== $request->email) {
            return back()->with('responseErrors', [trans('texts.cross_migration_message')]);
        }

        $authentication = (new AuthService($request->email, $request->password))
            ->endpoint(session('MIGRATION_ENDPOINT'))
            ->start();

        if ($authentication->isSuccessful()) {
            session()->put('MIGRATION_ACCOUNT_TOKEN', $authentication->getAccountToken());

            return redirect('/migration/companies');
        }

        return back()->with('responseErrors', $authentication->getErrors());
    }

    public function companies()
    {
        if ($this->shouldGoBack('companies'))
            return redirect($this->access['companies']['redirect']);

        $companyService = (new CompanyService(session('MIGRATION_ACCOUNT_TOKEN')))
            ->endpoint(session('MIGRATION_ENDPOINT'))
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
        if ($this->shouldGoBack('companies'))
            return redirect($this->access['companies']['redirect']);

        foreach ($request->companies as $company) {
            (new CompleteService(session('MIGRATION_ACCOUNT_TOKEN')))
                ->file($this->getMigrationFile())
                ->force(array_key_exists('force', $company))
                ->company($company['id'])
                ->endpoint(session('MIGRATION_ENDPOINT'))
                ->companyKey($request->account_key)
                ->start();
        }

        return view('migration.completed');
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
    public function getMigrationFile()
    {
        $this->account = Auth::user()->account;

        $date = date('Y-m-d');
        $accountKey = $this->account->account_key;

        $output = fopen('php://output', 'w') or Utils::fatalError();

        $fileName = "{$accountKey}-{$date}-invoiceninja";

        $data = [
            'company' => $this->getCompany(),
            'users' => $this->getUsers(),
            'tax_rates' => $this->getTaxRates(),
            'payment_terms' => $this->getPaymentTerms(),
            'clients' => $this->getClients(),
            'products' => $this->getProducts(),
            'invoices' => $this->getInvoices(),
            'quotes' => $this->getQuotes(),
            'payments' => array_merge($this->getPayments(), $this->getCredits()),
            'credits' => $this->getCreditsNotes(),
            'documents' => $this->getDocuments(),
            'company_gateways' => $this->getCompanyGateways(),
            'client_gateway_tokens' => $this->getClientGatewayTokens(),
        ];

        $file = storage_path("migrations/{$fileName}.zip");

        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('migration.json', json_encode($data, JSON_PRETTY_PRINT));
        $zip->close();

        // header('Content-Type: application/zip');
        // header('Content-Length: ' . filesize($file));
        // header("Content-Disposition: attachment; filename={$fileName}.zip");

        return $file;
    }
}
