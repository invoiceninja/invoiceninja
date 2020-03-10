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
    protected $signature = 'migrations:export {--user=}';

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

        $users = User::all();

        foreach($users as $user) {
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

        $data = [
            'company' => $this->getCompany(),
            'users' => $this->getUsers(),
            'tax_rates' => $this->getTaxRates(),
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

        $this->info('User with id #' . $user->id . ' exported.');
    }
}
