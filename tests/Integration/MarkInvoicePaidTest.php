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

namespace Tests\Integration;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class MarkInvoicePaidTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testClientExists()
    {
        $this->assertNotNull($this->client);
    }

    public function testMarkInvoicePaidInvoice()
    {
        $invoice = Invoice::find($this->invoice->id);
        $invoice_balance = $invoice->balance;
        $client = $invoice->client;
        $client_balance = $client->balance;

        $this->invoice->service()->markPaid();

        $invoice = Invoice::find($this->invoice->id);
        $client = $invoice->client;

        $this->assertEquals(0.00, $invoice->balance);

        $this->assertEquals(1, count($invoice->payments));

        foreach ($invoice->payments as $payment) {
            $this->assertEquals(round($this->invoice->amount, 2), $payment->amount);
        }

        //events are not firing which makes this impossible to control.

        $this->assertEquals(0.00, $invoice->balance);
        $this->assertEquals(($client_balance - $invoice_balance), $client->balance);
    }
}
