<?php
/**
 * Credit Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Bank;

use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Bank\Yodlee\Yodlee;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Libraries\MultiDB;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Bank\BankService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class MatchBankTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeneratesCounter;

    private int $company_id;

    private string $db;

    private array $input;

    protected Company $company;

    public Invoice $invoice;

    private BankTransaction $bt;

    private $categories;
    /**
     * Create a new job instance.
     */
    public function __construct(int $company_id, string $db, array $input)
    {

        $this->company_id = $company_id;
        $this->db = $db;
        $this->input = $input;
        $this->categories = collect();

    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        MultiDB::setDb($this->db);

        $this->company = Company::find($this->company_id);

        $yodlee = new Yodlee($this->company->account->bank_integration_account_id);
        
        $_categories = $yodlee->getTransactionCategories();

        if($_categories)
            $this->categories = collect($_categories->transactionCategory);

        foreach($this->input as $match)
        {
            if(array_key_exists('invoice_id', $match) && strlen($match['invoice_id']) > 1)
                $this->matchInvoicePayment($match);
            elseif(array_key_exists('is_expense', $match) && $match['is_expense'])
                $this->matchExpense($match);
        }

    }

    private function matchInvoicePayment(array $match) :void
    {
        $this->bt = BankTransaction::find($match['id']);

        $_invoice = Invoice::withTrashed()->find($match['invoice_id']);

        if(array_key_exists('amount', $match) && $match['amount'] > 0)
            $amount = $match['amount'];
        else
            $amount = $this->bt->amount;

        if($_invoice && $_invoice->isPayable()){

            $this->createPayment($match['invoice_id'], $amount);

        }

    }

    private function matchExpense(array $match) :void
    {
        //if there is a category id, pull it from Yodlee and insert - or just reuse!!
        $this->bt = BankTransaction::find($match['id']);

        $expense = ExpenseFactory::create($this->bt->company_id, $this->bt->user_id);
        $expense->category_id = $this->resolveCategory();
        $expense->amount = $this->bt->amount;
        $expense->number = $this->getNextExpenseNumber($expense);
        $expense->currency_id = $this->harvestCurrencyId();
        $expense->date = Carbon::parse($this->bt->date);
        $expense->public_notes = $this->bt->description;
        $expense->save();

        nlog($expense->toArray());

    }

    private function createPayment(int $invoice_id, float $amount) :void
    {

        \DB::connection(config('database.default'))->transaction(function () use($invoice_id, $amount) {

            $this->invoice = Invoice::withTrashed()->where('id', $invoice_id)->lockForUpdate()->first();

            $this->invoice
                ->service()
                ->setExchangeRate()
                ->updateBalance($amount * -1)
                ->updatePaidToDate($amount)
                ->setCalculatedStatus()
                ->save();

        }, 1);

        /* Create Payment */
        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $amount;
        $payment->applied = $amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $this->invoice->client_id;
        $payment->transaction_reference = $this->bt->transaction_id;
        $payment->currency_id = $this->harvestCurrencyId();
        $payment->is_manual = false;
        $payment->date = $this->bt->date ? Carbon::parse($this->bt->date) : now();
        

        /* Bank Transfer! */
        $payment_type_id = 1;

        $payment->saveQuietly();

        $payment->service()->applyNumber()->save();
        
        if($payment->client->getSetting('send_email_on_mark_paid'))
            $payment->service()->sendEmail();

        $this->setExchangeRate($payment);

        /* Create a payment relationship to the invoice entity */
        $payment->invoices()->attach($this->invoice->id, [
            'amount' => $amount,
        ]);

        event('eloquent.created: App\Models\Payment', $payment);

        $this->invoice->next_send_date = null;

        $this->invoice
                ->service()
                ->applyNumber()
                ->touchPdf()
                ->save();

        $payment->ledger()
                ->updatePaymentBalance($amount * -1);

        $this->invoice
             ->client
             ->service()
             ->updateBalanceAndPaidToDate($amount*-1, $amount)
             ->save();

        $this->invoice = $this->invoice
                             ->service()
                             ->workFlow()
                             ->save();

        /* Update Invoice balance */
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $this->bt->is_matched = true;
        $this->bt->save();
    }

    private function resolveCategory() :?int
    {
        $category = $this->categories->firstWhere('highLevelCategoryId', $this->bt->category_id);

        $ec = ExpenseCategory::where('company_id', $this->bt->company_id)->where('bank_category_id', $this->bt->category_id)->first();

        if($ec)
            return $ec->id;

        if($category)
        {

            $ec = ExpenseCategoryFactory::create($this->bt->company_id, $this->bt->user_id);
            $ec->bank_category_id = $this->bt->category_id;
            $ec->name = $category->highLevelCategoryName;
            $ec->save();

            return $ec->id;
        }

        return null;
    }

    private function harvestCurrencyId() :int
    {
        $currency = Currency::where('code', $this->bt->currency_code)->first();

        if($currency)
            return $currency->id;

        return $this->invoice->client->getSetting('currency_id');

    }

    private function setExchangeRate(Payment $payment)
    {
        if ($payment->exchange_rate != 1) {
            return;
        }

        $client_currency = $payment->client->getSetting('currency_id');
        $company_currency = $payment->client->company->settings->currency_id;

        if ($company_currency != $client_currency) {
            $exchange_rate = new CurrencyApi();

            $payment->exchange_rate = $exchange_rate->exchangeRate($client_currency, $company_currency, Carbon::parse($payment->date));
            $payment->exchange_currency_id = $company_currency;

            $payment->saveQuietly();
        }
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company_id)];
    }





}