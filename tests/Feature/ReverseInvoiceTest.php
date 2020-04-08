<?php

namespace Tests\Feature;

use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Listeners\Credit\CreateCreditInvitation;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
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

    public function setUp() :void
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

    	$this->assertTrue($this->invoice->invoiceReversable($this->invoice));

        $balance_remaining = $this->invoice->balance;
        $total_paid = $this->invoice->amount - $this->invoice->balance;

        /*Adjust payment applied and the paymentables to the correct amount */

        $paymentables = Paymentable::wherePaymentableType(Invoice::class)
        							->wherePaymentableId($this->invoice->id)
        							->get();

		$paymentables->each(function ($paymentable) use($total_paid){

        	$reversable_amount = $paymentable->amount - $paymentable->refunded;

        	$total_paid -= $reversable_amount;

        	$paymentable->amount = $paymentable->refunded;
        	$paymentable->save();

        	$payment = $paymentable->payment;

        	$payment->applied -= $reversable_amount;
        	$payment->save();
        	
		});

		/* Generate a credit for the $total_paid amount */
		$credit = CreditFactory::create($this->invoice->company_id, $this->invoice->user_id);
		$credit->client_id = $this->invoice->client_id;

			$item = InvoiceItemFactory::create();
            $item->quantity = 1;
            $item->cost = (float)$total_paid;
            $item->notes = "Credit for reversal of ".$this->invoice->number;

            $line_items[] = $item;

        $credit->line_items = $line_items;

        $credit->save();

        $credit_calc = new InvoiceSum($credit);
        $credit_calc->build();

        $credit = $credit_calc->getCredit();

        $credit->service()->markSent()->save();

		/* Set invoice balance to 0 */
		$this->invoice->balance = 0;
		$this->invoice->save();

		/* Set invoice status to reversed... somehow*/

		/* Reduce client.paid_to_date by $total_paid amount */
		$this->client->paid_to_date -= $total_paid;

		/* Reduce the client balance by $balance_remaining */

		$this->client->balance -= $balance_remaining;

		$this->client->save();

		$this->assertEquals(0, $this->invoice->balance);
		$this->assertEquals($total_paid, $credit->balance);
    }

}


// $this->invoice->payments->each(function ($payment) use($total_paid){

//     $payment->paymentables->each(function ($paymentable) use($total_paid){

//     	$reversable_amount = $paymentable->amount - $paymentable->refunded;

//     	$total_paid -= $reversable_amount;

//     	$paymentable->amount = $paymentable->refunded;
//     	$paymentable->save();

//     	$payment->applied -= $reversable_amount;
//     	$payment->save();

//     });

// });