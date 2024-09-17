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

namespace Tests\Unit;

use App\Factory\PaymentFactory;
use App\Utils\Traits\Invoice\ActionsInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *   App\Utils\Traits\Invoice\ActionsInvoice
 */
class InvoiceActionsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use ActionsInvoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceIsDeletable()
    {
        $this->assertFalse($this->invoiceDeletable($this->invoice));
        $this->assertTrue($this->invoiceReversable($this->invoice));
        $this->assertTrue($this->invoiceCancellable($this->invoice));
    }

    public function testInvoiceIsReversable()
    {

        $this->invoice = $this->invoice->service()->markPaid()->save();

        $this->assertFalse($this->invoiceDeletable($this->invoice));
        $this->assertTrue($this->invoiceReversable($this->invoice));
        $this->assertFalse($this->invoiceCancellable($this->invoice));
    }

    public function testInvoiceIsCancellable()
    {

        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);
        $payment->amount = 40;
        $payment->client_id = $this->invoice->client_id;
        $payment->applied = 0;
        $payment->refunded = 0;
        $payment->date = now();
        $payment->save();

        $this->invoice->service()->applyPayment($payment, 5)->save();

        $this->assertFalse($this->invoiceDeletable($this->invoice));
        $this->assertTrue($this->invoiceReversable($this->invoice));
        $this->assertTrue($this->invoiceCancellable($this->invoice));
    }

    public function testInvoiceUnactionable()
    {

        $this->invoice->delete();

        $this->assertFalse($this->invoiceDeletable($this->invoice));
        $this->assertFalse($this->invoiceReversable($this->invoice));
        $this->assertFalse($this->invoiceCancellable($this->invoice));
    }
}
