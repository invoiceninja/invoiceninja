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
use App\Helpers\Invoice\InvoiceItemSumInclusive;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *   App\Helpers\Invoice\InvoiceItemSumInclusive
 */
class InvoiceItemInclusiveTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testInvoiceItemTotalSimpleX()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 10);
    }

    public function testInvoiceItemTotalSimpleWithDiscount()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 8);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecision()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 7.48);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 0;
        $item->tax_rate1 = 10;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 0.91);
        $this->assertEquals($item_calc->getSubTotal(), 10);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleInclusiveTax2()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;
        $item->tax_rate1 = 10;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 0.73);
        $this->assertEquals($item_calc->getSubTotal(), 8);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 0;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        // Tax-anchored: tax1=round(10*10/127.5)=0.78, tax2=round(10*17.5/127.5)=1.37 => 2.15
        $this->assertEquals($item_calc->getTotalTaxes(), 2.15);
        $this->assertEquals($item_calc->getSubTotal(), 10);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithDoubleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 1;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        // Tax-anchored on 9: tax1=round(9*10/127.5)=0.71, tax2=round(9*17.5/127.5)=1.24 => 1.95
        $this->assertEquals($item_calc->getSubTotal(), 9);
        $this->assertEquals($item_calc->getTotalTaxes(), 1.95);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithDoubleInclusiveTaxMultiQuantity()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 1;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        // Tax-anchored on 19: tax1=round(19*10/127.5)=1.49, tax2=round(19*17.5/127.5)=2.61 => 4.10
        $this->assertEquals($item_calc->getSubTotal(), 19);
        $this->assertEquals($item_calc->getTotalTaxes(), 4.10);
    }

    public function testInvoiceItemTotalSimpleWithPercentDiscountWithDoubleInclusiveTaxMultiQuantity()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 10;
        $item->is_amount_discount = false;
        $item->discount = 1;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];
        $this->invoice->is_amount_discount = false;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        // Tax-anchored on 19.8: tax1=round(19.8*10/127.5)=1.55, tax2=round(19.8*17.5/127.5)=2.72 => 4.27
        $this->assertEquals($item_calc->getSubTotal(), 19.8);
        $this->assertEquals($item_calc->getTotalTaxes(), 4.27);
    }

    /**
     * Canonical case: 1000 inclusive with 2 x 10%.
     * Tax-anchored additive: each tax = round(1000*10/120) = 83.33, total 166.66,
     * net = 1000 - 166.66 = 833.34 (net absorbs the residual).
     * The old overlapping formula returned 181.82 (net 818.18).
     */
    public function testInclusiveDoubleTaxCanonicalReconciles()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 1000;
        $item->is_amount_discount = false;
        $item->discount = 0;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $item->tax_name2 = 'PST';
        $item->tax_rate2 = 10;

        $this->invoice->line_items = [$item];
        $this->invoice->is_amount_discount = false;
        $this->invoice->discount = 0;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals(1000, $item_calc->getSubTotal());
        $this->assertEquals(166.66, $item_calc->getTotalTaxes());

        // net + tax MUST equal the gross exactly (net absorbs the residual)
        $net = round($item_calc->getSubTotal() - $item_calc->getTotalTaxes(), 2);
        $this->assertEquals(833.34, $net);
        $this->assertEquals(1000, round($net + $item_calc->getTotalTaxes(), 2));

        // per-tax breakdown must sum EXACTLY to the total tax
        $grouped_sum = $item_calc->getGroupedTaxes()->sum(fn ($t) => $t['total']);
        $this->assertEquals(166.66, round($grouped_sum, 2));
    }

    /**
     * Inclusive mode must be the exact inverse of exclusive mode.
     * Exclusive net 1000 @ 2x10% -> gross 1200. Re-entered inclusive at 1200
     * must recover net 1000 / tax 200 exactly.
     */
    public function testInclusiveIsInverseOfExclusive()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 1200;
        $item->is_amount_discount = false;
        $item->discount = 0;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $item->tax_name2 = 'PST';
        $item->tax_rate2 = 10;

        $this->invoice->line_items = [$item];
        $this->invoice->is_amount_discount = false;
        $this->invoice->discount = 0;

        $item_calc = new InvoiceItemSumInclusive($this->invoice);
        $item_calc->process();

        $this->assertEquals(1200, $item_calc->getSubTotal());
        $this->assertEquals(200, $item_calc->getTotalTaxes());
        $this->assertEquals(1000, round($item_calc->getSubTotal() - $item_calc->getTotalTaxes(), 2));
    }
}
