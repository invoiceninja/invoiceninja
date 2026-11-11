<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Proves the theory that App\Helpers\Invoice\InvoiceSum::setCalculatedAttributes()
 * discards paid_to_date whenever amount == balance at recalculation time.
 *
 * This is the corruption that produced the reported invoice where
 * amount == balance == paid_to_date and status_id == STATUS_PAID.
 *
 *   App\Helpers\Invoice\InvoiceSum
 */
class InvoiceBalancePaidToDateTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    private function makeSentInvoice(float $cost, int $quantity = 1): Invoice
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = $quantity;
        $item->cost = $cost;
        $item->tax_rate1 = 0;
        $item->tax_name1 = '';

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = true;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_rate2 = 0;
        $invoice->tax_rate3 = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_name2 = '';
        $invoice->tax_name3 = '';
        $invoice->status_id = Invoice::STATUS_SENT;
        $invoice->line_items = [(object) (array) $item];
        $invoice->saveQuietly();

        return $invoice->fresh();
    }

    /**
     * CONTROL: when amount != balance the IF branch is taken and
     * paid_to_date is correctly subtracted. This passes today and must
     * keep passing after any fix - it proves the bug is the *branch
     * selection*, not the calculator in general.
     */
    public function test_balance_is_correct_when_amount_differs_from_balance(): void
    {
        $invoice = $this->makeSentInvoice(1000);

        $invoice->amount = 1000;
        $invoice->balance = 600;       // amount != balance -> IF branch
        $invoice->paid_to_date = 400;
        $invoice->saveQuietly();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(1000, $invoice->amount);
        $this->assertEquals(600, $invoice->balance, 'IF branch correctly yields total - paid_to_date');
    }

    /**
     * REGRESSION: a partially paid invoice (amount 1000, balance 600,
     * paid_to_date 400) whose line-item total is edited up to 1200 must
     * move the balance by the +200 delta to 800, while paid_to_date is
     * left untouched. The OLD ELSE branch wiped this to balance = 1200.
     */
    public function test_editing_total_on_partially_paid_invoice_applies_delta_only(): void
    {
        $invoice = $this->makeSentInvoice(1000);

        $invoice->amount = 1000;
        $invoice->balance = 600;
        $invoice->paid_to_date = 400;
        $invoice->saveQuietly();

        // Edit the line items: new total 1200 (+200 delta)
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 1200;
        $item->tax_rate1 = 0;
        $item->tax_name1 = '';
        $invoice->line_items = [(object) (array) $item];

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(1200, $invoice->amount, 'amount follows the new total');
        $this->assertEquals(800, $invoice->balance, 'balance moves by the +200 delta, not reset to total');
        $this->assertEquals(400, $invoice->paid_to_date, 'paid_to_date is never consulted or changed');
    }

    /**
     * REGRESSION: a fully paid invoice (balance 0, amount == paid_to_date)
     * whose total is edited up by 250 must owe exactly the 250 increase,
     * NOT the full new amount. This is the exact corruption shape from the
     * reported production invoice.
     */
    public function test_editing_total_on_fully_paid_invoice_does_not_strand_payment(): void
    {
        $invoice = $this->makeSentInvoice(69750);

        $invoice->amount = 69750;
        $invoice->balance = 0;
        $invoice->paid_to_date = 69750;
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->saveQuietly();

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 70000;          // +250
        $item->tax_rate1 = 0;
        $item->tax_name1 = '';
        $invoice->line_items = [(object) (array) $item];

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(70000, $invoice->amount);
        $this->assertEquals(250, $invoice->balance, 'only the +250 increase is owed, payment retained');
        $this->assertEquals(69750, $invoice->paid_to_date);
    }

    /**
     * A correctly fully-paid invoice (balance 0, amount == paid_to_date)
     * must remain at balance 0 across repeated recalculations. This is the
     * idempotency guarantee the reminder/cron sweeps depend on. Passes today
     * via the IF branch; must keep passing after the fix.
     */
    public function test_fully_paid_invoice_recalculation_is_idempotent(): void
    {
        $invoice = $this->makeSentInvoice(1000);

        $invoice->amount = 1000;
        $invoice->balance = 0;
        $invoice->paid_to_date = 1000;
        $invoice->status_id = Invoice::STATUS_PAID;
        $invoice->saveQuietly();

        $invoice = $invoice->calc()->getInvoice();
        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(0, $invoice->balance, 'Fully paid invoice must stay at balance 0 across recalculations');
    }
}
