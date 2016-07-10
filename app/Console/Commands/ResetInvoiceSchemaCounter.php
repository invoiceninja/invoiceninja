<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ResetInvoiceSchemaCounter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:reset-invoice-schema-counter
                            {account? : The ID of the account}
                            {--force : Force setting the counter back to "1", regardless if the year changed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the invoice schema counter at the turn of the year.';

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * Create a new command instance.
     *
     * @param Invoice $invoice
     */
    public function __construct(Invoice $invoice)
    {
        parent::__construct();
        $this->invoice = $invoice;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $force = $this->option('force');
        $account = $this->argument('account');

        $accounts = null;

        if ($account) {
            $accounts = Account::find($account)->get();
        } else {
            $accounts = Account::all();
        }

        $latestInvoice = $this->invoice->latest()->first();
        $invoiceYear = Carbon::parse($latestInvoice->created_at)->year;

        if(Carbon::now()->year > $invoiceYear || $force) {
            $accounts->transform(function ($a) {
                /** @var Account $a */
                $a->invoice_number_counter = 1;
                $a->update();
            });

            $this->info('The counter has been resetted successfully for '.$accounts->count().' account(s).');
        }
    }
}
