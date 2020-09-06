<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Console\Commands\TestData;

use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Product;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class CreateTestInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    protected $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $faker = \Faker\Factory::create();

        $invoice = InvoiceFactory::create($this->client->company->id, $this->client->user->id); //stub the company and user_id
        $invoice->client_id = $this->client->id;
//        $invoice->date = $faker->date();
        $dateable = Carbon::now()->subDays(rand(0, 90));
        $invoice->date = $dateable;

        $invoice->line_items = $this->buildLineItems(rand(1, 10));
        $invoice->uses_inclusive_taxes = false;

        if (rand(0, 1)) {
            $invoice->tax_name1 = 'GST';
            $invoice->tax_rate1 = 10.00;
        }

        if (rand(0, 1)) {
            $invoice->tax_name2 = 'VAT';
            $invoice->tax_rate2 = 17.50;
        }

        if (rand(0, 1)) {
            $invoice->tax_name3 = 'CA Sales Tax';
            $invoice->tax_rate3 = 5;
        }

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        $invoice->save();
        $invoice->service()->createInvitations()->markSent()->save();

        $invoice->ledger()->updateInvoiceBalance($invoice->balance);

        //UpdateCompanyLedgerWithInvoice::dispatchNow($invoice, $invoice->balance, $invoice->company);

        //$this->invoice_repo->markSent($invoice);

        if (rand(0, 1)) {
            $payment = PaymentFactory::create($this->client->company->id, $this->client->user->id);
            $payment->date = $dateable;
            $payment->client_id = $this->client->id;
            $payment->amount = $invoice->balance;
            $payment->transaction_reference = rand(0, 500);
            $payment->type_id = PaymentType::CREDIT_CARD_OTHER;
            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->number = $this->client->getNextPaymentNumber($this->client);
            $payment->currency_id = 1;
            $payment->save();

            $payment->invoices()->save($invoice);

            event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

            $payment->service()->updateInvoicePayment();
            //UpdateInvoicePayment::dispatchNow($payment, $payment->company);
        }
        //@todo this slow things down, but gives us PDFs of the invoices for inspection whilst debugging.
        event(new InvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars()));
    }

    private function buildLineItems($count = 1)
    {
        $line_items = [];

        for ($x = 0; $x < $count; $x++) {
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            //$item->cost = 10;

            if (rand(0, 1)) {
                $item->tax_name1 = 'GST';
                $item->tax_rate1 = 10.00;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'VAT';
                $item->tax_rate1 = 17.50;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'Sales Tax';
                $item->tax_rate1 = 5;
            }

            $product = Product::all()->random();

            $item->cost = (float) $product->cost;
            $item->product_key = $product->product_key;
            $item->notes = $product->notes;
            $item->custom_value1 = $product->custom_value1;
            $item->custom_value2 = $product->custom_value2;
            $item->custom_value3 = $product->custom_value3;
            $item->custom_value4 = $product->custom_value4;

            $line_items[] = $item;
        }

        return $line_items;
    }
}
