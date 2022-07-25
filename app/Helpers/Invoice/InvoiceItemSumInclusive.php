<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Invoice;

use App\Models\Invoice;
use App\Utils\Traits\NumberFormatter;

class InvoiceItemSumInclusive
{
    use NumberFormatter;
    use Discounter;
    use Taxer;

    protected $invoice;

    private $items;

    private $line_total;

    private $currency;

    private $total_taxes;

    private $item;

    private $line_items;

    private $sub_total;

    private $total_discount;

    private $tax_collection;

    public function __construct($invoice)
    {
        $this->tax_collection = collect([]);

        $this->invoice = $invoice;

        if ($this->invoice->client) {
            $this->currency = $this->invoice->client->currency();
        } else {
            $this->currency = $this->invoice->vendor->currency();
        }

        $this->line_items = [];
    }

    public function process()
    {
        if (! $this->invoice->line_items || ! is_array($this->invoice->line_items) || count($this->invoice->line_items) == 0) {
            $this->items = [];

            return $this;
        }

        $this->calcLineItems();

        return $this;
    }

    private function calcLineItems()
    {
        foreach ($this->invoice->line_items as $this->item) {
            $this->sumLineItem()
                ->setDiscount()
                ->calcTaxes()
                ->push();
        }

        return $this;
    }

    private function push()
    {
        $this->sub_total += $this->getLineTotal();

        $this->line_items[] = $this->item;

        return $this;
    }

    private function sumLineItem()
    {
        $this->setLineTotal($this->item->cost * $this->item->quantity);

        return $this;
    }

    private function setDiscount()
    {
        if ($this->invoice->is_amount_discount) {
            $this->setLineTotal($this->getLineTotal() - $this->formatValue($this->item->discount, $this->currency->precision));
        } else {
            $this->setLineTotal($this->getLineTotal() - $this->formatValue(($this->item->line_total * ($this->item->discount / 100)), $this->currency->precision));
        }

        $this->item->is_amount_discount = $this->invoice->is_amount_discount;

        return $this;
    }

    /**
     * Taxes effect the line totals and item costs. we decrement both on
     * application of inclusive tax rates.
     */
    private function calcTaxes()
    {
        $item_tax = 0;

        $amount = $this->item->line_total - ($this->item->line_total * ($this->invoice->discount / 100));

        $item_tax_rate1_total = $this->calcInclusiveLineTax($this->item->tax_rate1, $amount);

        $item_tax += $this->formatValue($item_tax_rate1_total, $this->currency->precision);

        // if($item_tax_rate1_total != 0)
        if (strlen($this->item->tax_name1) > 1) {
            $this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total);
        }

        $item_tax_rate2_total = $this->calcInclusiveLineTax($this->item->tax_rate2, $amount);

        $item_tax += $this->formatValue($item_tax_rate2_total, $this->currency->precision);

        if (strlen($this->item->tax_name2) > 1) {
            $this->groupTax($this->item->tax_name2, $this->item->tax_rate2, $item_tax_rate2_total);
        }

        $item_tax_rate3_total = $this->calcInclusiveLineTax($this->item->tax_rate3, $amount);

        $item_tax += $this->formatValue($item_tax_rate3_total, $this->currency->precision);

        if (strlen($this->item->tax_name3) > 1) {
            $this->groupTax($this->item->tax_name3, $this->item->tax_rate3, $item_tax_rate3_total);
        }

        $this->setTotalTaxes($this->formatValue($item_tax, $this->currency->precision));

        return $this;
    }

    private function groupTax($tax_name, $tax_rate, $tax_total)
    {
        $group_tax = [];

        $key = str_replace(' ', '', $tax_name.$tax_rate);

        $group_tax = ['key' => $key, 'total' => $tax_total, 'tax_name' => $tax_name.' '.$tax_rate.'%'];

        $this->tax_collection->push(collect($group_tax));
    }

    public function getTotalTaxes()
    {
        return $this->total_taxes;
    }

    public function setTotalTaxes($total)
    {
        $this->total_taxes = $total;

        return $this;
    }

    public function setLineTotal($total)
    {
        $this->item->gross_line_total = $total;

        $this->item->line_total = $total;

        return $this;
    }

    public function getLineTotal()
    {
        return $this->item->line_total;
    }

    public function getGrossLineTotal()
    {
        return $this->item->line_total;
    }

    public function getLineItems()
    {
        return $this->line_items;
    }

    public function getGroupedTaxes()
    {
        return $this->tax_collection;
    }

    public function setGroupedTaxes($group_taxes)
    {
        $this->tax_collection = $group_taxes;

        return $this;
    }

    public function getSubTotal()
    {
        return $this->sub_total;
    }

    public function getGrossSubTotal()
    {
        return $this->sub_total;
    }

    public function setSubTotal($value)
    {
        $this->sub_total = $value;

        return $this;
    }

    /**
     * Invoice Amount Discount.
     *
     * The problem, when calculating invoice level discounts,
     * the tax collected changes.
     *
     * We need to synthetically reduce the line_total amounts
     * and recalculate the taxes and then pass back
     * the updated map
     */
    public function calcTaxesWithAmountDiscount()
    {
        $this->setGroupedTaxes(collect([]));

        $item_tax = 0;

        foreach ($this->line_items as $this->item) {
            if ($this->sub_total == 0) {
                $amount = $this->item->line_total;
            } else {
                $amount = $this->item->line_total - ($this->item->line_total * ($this->invoice->discount / $this->sub_total));
            }

            $item_tax_rate1_total = $this->calcInclusiveLineTax($this->item->tax_rate1, $amount);

            $item_tax += $item_tax_rate1_total;

            if ($item_tax_rate1_total != 0) {
                $this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total);
            }

            $item_tax_rate2_total = $this->calcInclusiveLineTax($this->item->tax_rate2, $amount);

            $item_tax += $item_tax_rate2_total;

            if ($item_tax_rate2_total != 0) {
                $this->groupTax($this->item->tax_name2, $this->item->tax_rate2, $item_tax_rate2_total);
            }

            $item_tax_rate3_total = $this->calcInclusiveLineTax($this->item->tax_rate3, $amount);

            $item_tax += $item_tax_rate3_total;

            if ($item_tax_rate3_total != 0) {
                $this->groupTax($this->item->tax_name3, $this->item->tax_rate3, $item_tax_rate3_total);
            }
        }

        $this->setTotalTaxes($item_tax);
    }
}
