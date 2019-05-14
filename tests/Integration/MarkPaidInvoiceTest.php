<?php

namespace Tests\Integration;

use App\Jobs\Invoice\MarkPaid;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Jobs\Invoice\MarkPaid
 */
class MarkPaidInvoiceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testClientExists()
    {
        $this->assertNotNull($this->client);
    }

    public function testMarkPaidInvoice()
    {
        MarkPaid::dispatchNow($this->invoice);

        $invoice = Invoice::find($this->invoice->id);

        $this->assertEquals(0.00, $invoice->balance);

        $this->assertEquals(1, count($invoice->payments));

        foreach($invoice->payments as $payment) {
            //Log::error($payment);
            $this->assertEquals(10, $payment->amount);
        }

        $this->assertEquals(Invoice::STATUS_PAID, $invoice->status_id);

        $this->assertEquals(0.00, $invoice->balance);

    }

}