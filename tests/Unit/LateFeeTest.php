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

use App\DataMapper\InvoiceItem;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class LateFeeTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testLateFeeBalances()
    {
        $this->assertEquals(10, $this->client->balance);
        $this->assertEquals(10, $this->invoice->balance);

        $this->invoice = $this->setLateFee($this->invoice, 5, 0);

        $this->assertEquals(15, $this->client->fresh()->balance);
        $this->assertEquals(15, $this->invoice->fresh()->balance);
    }

    private function setLateFee($invoice, $amount, $percent) :Invoice
    {
        $temp_invoice_balance = $invoice->balance;

        if ($amount <= 0 && $percent <= 0) {
            return $invoice;
        }

        $fee = $amount;

        if ($invoice->partial > 0) {
            $fee += round($invoice->partial * $percent / 100, 2);
        } else {
            $fee += round($invoice->balance * $percent / 100, 2);
        }

        $invoice_item = new InvoiceItem;
        $invoice_item->type_id = '5';
        $invoice_item->product_key = trans('texts.fee');
        $invoice_item->notes = ctrans('texts.late_fee_added', ['date' => now()]);
        $invoice_item->quantity = 1;
        $invoice_item->cost = $fee;

        $invoice_items = $invoice->line_items;
        $invoice_items[] = $invoice_item;

        $invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $invoice = $invoice->calc()->getInvoice();

        $invoice->client->service()->updateBalance($invoice->balance - $temp_invoice_balance)->save();
        $invoice->ledger()->updateInvoiceBalance($invoice->balance - $temp_invoice_balance, "Late Fee Adjustment for invoice {$invoice->number}");

        return $invoice;
    }
}
