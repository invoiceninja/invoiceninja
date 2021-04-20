<?php

namespace App\Console\Commands;

use App\Libraries\Utils;
use App\Models\User;
use App\Traits\GenerateMigrationResources;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class ExportMigrations extends Command
{
    use GenerateMigrationResources;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrations:export {--user=} {--random=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export account migrations to folder.';

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
        $this->info('Note: Migrations will be stored inside of (storage/migrations) folder.');

        if($this->option('user')) {
            $record = User::findOrFail($this->option('user'));
            return $this->export($record);
        }

        if($this->option('random')){

            User::all()->random(200)->each(function ($user){
                 $this->export($user);
            });

            return;
        }

        $users = User::all();

        foreach($users as $user) {
            Auth::login($user);
            $this->export($user);
        }
    }

    private function export($user)
    {
        $this->account = $user->account;

        $date = date('Y-m-d');
        $accountKey = $this->account->account_key;

        $output = fopen('php://output', 'w') or Utils::fatalError();

        $fileName = "{$accountKey}-{$date}-invoiceninja";

        $data['data'] = [
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
            'recurring_invoices' => $this->getRecurringInvoices(),
            'quotes' => $this->getQuotes(),
            'payments' => array_merge($this->getPayments(), $this->getCredits()),
            'documents' => $this->getDocuments(),
            'expense_categories' => $this->getExpenseCategories(),
            'task_statuses' => $this->getTaskStatuses(),
            'expenses' => $this->getExpenses(),
            'tasks' => $this->getTasks(),
            'documents' => $this->getDocuments(),
        ];

        $file = storage_path("migrations/{$fileName}.zip");

        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('migration.json', json_encode($data, JSON_PRETTY_PRINT));
        $zip->close();

        $this->info('User with id #' . $user->id . ' exported.');
    }
}
