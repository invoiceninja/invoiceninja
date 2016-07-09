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
                            {--force : Force setting the counter back to "1", regardless if the year changed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset the invoice schema counter at the turn of the year.';

    /**
     * @var Account
     */
    protected $account;

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * Create a new command instance.
     *
     * @param Account $account
     * @param Invoice $invoice
     */
    public function __construct(Account $account, Invoice $invoice)
    {
        parent::__construct();
        $this->account = $account;
        $this->invoice = $invoice;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $latestInvoice = $this->invoice->latest()->first();
        $invoiceYear = Carbon::parse($latestInvoice->created_at)->year;

        if(Carbon::now()->year > $invoiceYear || $this->option('force')) {
            $this->account->invoice_number_counter = 1;
            $this->account->update();
        }
    }
}
