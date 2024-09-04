<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Services\Invoice\HandleReversal
 */
class ReverseInvoiceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }

    public function testReverseInvoice()
    {
        $amount = $this->invoice->amount;
        $balance = $this->invoice->balance;

        $this->invoice->service()->markPaid()->save();

        $first_payment = $this->invoice->payments->first();

        $this->assertEquals((float) $first_payment->amount, (float) $this->invoice->amount);
        $this->assertEquals((float) $first_payment->applied, (float) $this->invoice->amount);

        $this->assertTrue($this->invoice->invoiceReversable($this->invoice));

        $balance_remaining = $this->invoice->balance;
        $total_paid = $this->invoice->amount - $this->invoice->balance;

        /*Adjust payment applied and the paymentables to the correct amount */

        $paymentables = Paymentable::wherePaymentableType(Invoice::class)
                                    ->wherePaymentableId($this->invoice->id)
                                    ->get();

        $paymentables->each(function ($paymentable) use ($total_paid) {
            $reversable_amount = $paymentable->amount - $paymentable->refunded;

            $total_paid -= $reversable_amount;

            $paymentable->amount = $paymentable->refunded;
            $paymentable->save();
        });

        /* Generate a credit for the $total_paid amount */
        $credit = CreditFactory::create($this->invoice->company_id, $this->invoice->user_id);
        $credit->client_id = $this->invoice->client_id;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = (float) $total_paid;
        $item->notes = 'Credit for reversal of '.$this->invoice->number;

        $line_items[] = $item;

        $credit->line_items = $line_items;

        $credit->save();

        $credit_calc = new InvoiceSum($credit);
        $credit_calc->build();

        $credit = $credit_calc->getCredit();

        $credit->service()
                ->setStatus(Credit::STATUS_SENT)
                ->markSent()->save();

        /* Set invoice balance to 0 */
        $this->invoice->ledger()->updateInvoiceBalance($balance_remaining * -1, $item->notes)->save();

        /* Set invoice status to reversed... somehow*/
        $this->invoice->service()->setStatus(Invoice::STATUS_REVERSED)->save();

        /* Reduce client.paid_to_date by $total_paid amount */
        $this->client->paid_to_date -= $total_paid;

        /* Reduce the client balance by $balance_remaining */
        $this->client->balance -= $balance_remaining;

        $this->client->save();

        //create a ledger row for this with the resulting Credit ( also include an explanation in the notes section )
    }

    public function testReversalViaAPI()
    {
        $this->assertEquals($this->client->balance, $this->invoice->balance);

        $client_paid_to_date = $this->client->paid_to_date;
        $client_balance = $this->client->balance;
        $invoice_balance = $this->invoice->balance;

        $this->assertEquals(Invoice::STATUS_SENT, $this->invoice->status_id);

        $this->invoice = $this->invoice->service()->markPaid()->save();

        $this->assertEquals($this->client->fresh()->balance, ($this->invoice->balance * -1));
        $this->assertEquals($this->client->fresh()->paid_to_date, ($client_paid_to_date + $invoice_balance));
        $this->assertEquals(0, $this->invoice->fresh()->balance);
        $this->assertEquals(Invoice::STATUS_PAID, $this->invoice->status_id);

        $this->invoice = $this->invoice->service()->handleReversal()->save();

        $this->assertEquals(Invoice::STATUS_REVERSED, $this->invoice->status_id);
        $this->assertEquals(0, $this->invoice->balance);
        $this->assertEquals($this->client->paid_to_date, ($client_paid_to_date));
    }

    public function testReversalNoPayment()
    {
        $this->assertEquals($this->client->balance, $this->invoice->balance);

        $client_paid_to_date = $this->client->paid_to_date;
        $client_balance = $this->client->balance;
        $invoice_balance = $this->invoice->balance;

        $this->assertEquals(Invoice::STATUS_SENT, $this->invoice->status_id);

        $this->invoice = $this->invoice->service()->handleReversal()->save();

        $this->assertEquals(Invoice::STATUS_REVERSED, $this->invoice->status_id);
        $this->assertEquals(0, $this->invoice->balance);
        $this->assertEquals($this->client->fresh()->paid_to_date, ($client_paid_to_date));
        $this->assertEquals($this->client->fresh()->balance, ($client_balance - $invoice_balance));
    }
}
