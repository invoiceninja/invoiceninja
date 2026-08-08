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

use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSumInclusive;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *   App\Helpers\Invoice\InvoiceSumInclusive
 */
class InvoiceInclusiveTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    public $invoice;

    public $invoice_calc;

    public $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->invoice->line_items = $this->buildLineItems();

        $this->invoice->uses_inclusive_taxes = true;

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
    }

    private function buildLineItems()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        return $line_items;
    }

    public function testInvoiceTotals()
    {

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals(round($this->invoice_calc->getSubTotal(), 0), 20);
        $this->assertEquals(round($this->invoice_calc->getTotal(), 0), 20);
    }

    public function testInvoiceTotalsWithDiscount()
    {
        $this->invoice->discount = 5;


        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 15);
        $this->assertEquals($this->invoice_calc->getBalance(), 15);
    }

    public function testInvoiceTotalsWithDiscountWithSurcharge()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;


        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithInclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->custom_surcharge_tax1 = false;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->is_amount_discount = true;
        $this->invoice->line_items = $this->buildLineItems();

        $calc = $this->invoice->calc();

        $this->assertEquals($calc->getSubTotal(), 20);
        $this->assertEquals($calc->getTotalTaxes(), 1.36);
        $this->assertEquals($calc->getTotal(), 20);
        $this->assertEquals($calc->getBalance(), 20);
    }

    public function testInvoiceTotalsWithPercentDiscountWithSurchargeWithInclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->is_amount_discount = false;

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.73);
        $this->assertEquals($this->invoice_calc->getTotal(), 24);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithExclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->is_amount_discount = true;

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.5);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithDoubleExclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_name2 = 'GST';
        $this->invoice->tax_rate2 = 10;
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->is_amount_discount = true;


        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        // Additive back-out: taxable 15 / 1.20 = net 12.5, total tax = 2.50
        // (old overlapping formula returned 2.72)
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 2.5);
    }

    /**
     * Proves groupTax() splits the total inclusive tax across two DISTINCT
     * named taxes correctly. Tax-anchored: 1000 @ GST 10% + PST 10% -> each tax
     * round(1000*10/120)=83.33, total 166.66, net 833.34; two map entries that
     * reconcile exactly, each reporting the shared net (833.34) as its base.
     */
    public function testInclusiveTaxMapSplitsTwoDistinctTaxes()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 1000;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $item->tax_name2 = 'PST';
        $item->tax_rate2 = 10;
        $item->tax_rate3 = 0;
        $item->tax_name3 = '';

        $this->invoice->line_items = [$item];
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 0;
        $this->invoice->custom_surcharge1 = 0;
        $this->invoice->tax_name1 = '';
        $this->invoice->tax_rate1 = 0;
        $this->invoice->tax_name2 = '';
        $this->invoice->tax_rate2 = 0;

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $this->assertEquals(1000, $this->invoice_calc->getSubTotal());
        $this->assertEquals(166.66, round($this->invoice_calc->getTotalTaxes(), 2));
        $this->assertEquals(1000, $this->invoice_calc->getTotal());

        $map = collect($this->invoice_calc->getTaxMap());

        // two distinct taxes -> two map entries
        $this->assertEquals(2, $map->count());

        // per-tax totals sum EXACTLY to the total tax
        $this->assertEquals(166.66, round($map->sum('total'), 2));

        // tax-anchored: each tax is independently round(base x rate) = 83.33
        $this->assertEqualsCanonicalizing([83.33, 83.33], $map->pluck('total')->map(fn ($t) => round($t, 2))->all());

        // every tax applies to the same shared net base
        $map->each(fn ($entry) => $this->assertEquals(833.34, round($entry['base_amount'], 2)));
        // each tax is exactly reproducible from the gross: round(1000 * rate / (100 + 20))
        $map->each(fn ($entry) => $this->assertEquals(round($entry['total'], 2), round(1000 * $entry['tax_rate'] / 120, 2)));
    }

    public function testLineItemTaxRatesInclusiveTaxes()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->tax_rate2 = 0;
        $item->tax_name2 = '';
        $item->tax_rate3 = 0;
        $item->tax_name3 = '';
        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->tax_rate2 = 0;
        $item->tax_name2 = '';
        $item->tax_rate3 = 0;
        $item->tax_name3 = '';

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 0;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.82);
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxes()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 0;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice->tax_name1 = 'dog';
        $this->invoice->tax_name2 = 'cat';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_rate2 = 10;

        $this->invoice_calc = null;
        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $this->assertEquals(20, round($this->invoice_calc->getSubTotal(), 0));
        // Tax-anchored: line items 2x0.91 = 1.82 + invoice-level 2x round(20*10/120)=2x1.67 = 3.34 => 5.16
        // (old overlapping formula returned 5.46)
        $this->assertEquals(5.16, $this->invoice_calc->getTotalTaxes());
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxesAndDiscounts()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 'a10';
        $item->discount = 5;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 'a10';
        $item->discount = 5;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 5;
        $this->invoice->is_amount_discount = false;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_rate2 = 10;
        $this->invoice->tax_name1 = 'VAT';
        $this->invoice->tax_name2 = 'VAT';

        $this->invoice_calc = null;
        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $line_items = $this->invoice_calc->invoice_items->getLineItems();
        // nlog($this->invoice_calc->getTaxMap());

        $this->assertEquals(19, $this->invoice_calc->getSubTotal());
        $this->assertEquals(0.95, $this->invoice_calc->getTotalDiscount());
        // Tax-anchored: line items 2x0.82 = 1.64 + invoice-level 2x round(18.05*10/120)=2x1.50 = 3.00 => 4.64
        // (old overlapping formula returned 4.92)
        $this->assertEquals(4.64, $this->invoice_calc->getTotalTaxes());


        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 18.05);
        $this->assertEquals($this->invoice_calc->getBalance(), 18.05);
    }

    public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxesAndAmountDiscounts()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->discount = 5;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->discount = 5;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 5;
        $this->invoice->is_amount_discount = true;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_rate2 = 10;

        $this->invoice->tax_name1 = 'dog';
        $this->invoice->tax_name2 = 'cat';

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $line_items = $this->invoice_calc->invoice_items->getLineItems();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 10);
        $this->assertEquals($this->invoice_calc->getTotalDiscount(), 5);
        // Tax-anchored: line items 2x0.23 = 0.46 + invoice-level 2x round(5*10/120)=2x0.42 = 0.84 => 1.30
        // (old overlapping formula returned 1.36)
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.30);
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 5);
        $this->assertEquals($this->invoice_calc->getBalance(), 5);
    }

    public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxesAndAmountDiscountsWithLargeCosts()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->discount = 5;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->discount = 5;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 5;
        $this->invoice->is_amount_discount = true;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_rate2 = 10;

        $this->invoice->tax_name1 = 'dog';
        $this->invoice->tax_name2 = 'cat';

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $line_items = $this->invoice_calc->invoice_items->getLineItems();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 190);
        $this->assertEquals($this->invoice_calc->getTotalDiscount(), 5);
        // Tax-anchored back-out of the double invoice-level tax (old overlap gave 50.46)
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 47.66);
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 185);
        $this->assertEquals($this->invoice_calc->getBalance(), 185);
    }

    public function testInvoicePersistsNegativeInclusiveTaxTotals(): void
    {
        $this->invoice->line_items = [$this->negativeInclusiveLineItem()];
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 0;
        $this->invoice->tax_name1 = '';
        $this->invoice->tax_name2 = '';
        $this->invoice->tax_name3 = '';
        $this->invoice->tax_rate1 = 0;
        $this->invoice->tax_rate2 = 0;
        $this->invoice->tax_rate3 = 0;

        $invoice = $this->invoice->calc()->getInvoice();

        $this->assertSame(90.0, (float) $invoice->amount);
        $this->assertSame(-10.0, (float) $invoice->total_taxes);
        $this->assertSame(-10.0, (float) $invoice->line_items[0]->tax_amount);
        $this->assertSame(100.0, (float) $invoice->line_items[0]->net_cost);
    }

    public function testCreditPersistsNegativeInclusiveTaxTotals(): void
    {
        $this->credit->line_items = [$this->negativeInclusiveLineItem()];
        $this->credit->uses_inclusive_taxes = true;
        $this->credit->discount = 0;
        $this->credit->tax_name1 = '';
        $this->credit->tax_name2 = '';
        $this->credit->tax_name3 = '';
        $this->credit->tax_rate1 = 0;
        $this->credit->tax_rate2 = 0;
        $this->credit->tax_rate3 = 0;

        $credit = $this->credit->calc()->getCredit();

        $this->assertSame(90.0, (float) $credit->amount);
        $this->assertSame(-10.0, (float) $credit->total_taxes);
        $this->assertSame(-10.0, (float) $credit->line_items[0]->tax_amount);
        $this->assertSame(100.0, (float) $credit->line_items[0]->net_cost);
    }

    public function testInvoiceGracefullyTreatsMalformedInclusiveTaxAsZero(): void
    {
        $item = $this->negativeInclusiveLineItem();
        $item->tax_name1 = 'Malformed Tax';
        $item->tax_rate1 = 'bad-rate';

        $this->invoice->line_items = [$item];
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 0;
        $this->invoice->tax_name1 = '';
        $this->invoice->tax_name2 = '';
        $this->invoice->tax_name3 = '';
        $this->invoice->tax_rate1 = 0;
        $this->invoice->tax_rate2 = 0;
        $this->invoice->tax_rate3 = 0;

        $invoice = $this->invoice->calc()->getInvoice();

        $this->assertSame(90.0, (float) $invoice->amount);
        $this->assertSame(0.0, (float) $invoice->total_taxes);
        $this->assertSame(0.0, (float) $invoice->line_items[0]->tax_amount);
        $this->assertSame(90.0, (float) $invoice->line_items[0]->net_cost);
    }

    private function negativeInclusiveLineItem(): \stdClass
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 90;
        $item->tax_name1 = 'Negative Tax';
        $item->tax_rate1 = -10;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;

        return $item;
    }
}
