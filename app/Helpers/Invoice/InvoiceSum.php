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
use App\Models\TaxRate;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Support\Collection;

class InvoiceSum
{
    use Taxer;
    use Balancer;
    use CustomValuer;
    use Discounter;
    use NumberFormatter;

    protected $invoice;

    public $tax_map;

    public $invoice_item;

    public $total_taxes = 0;

    private $total;

    private $total_discount;

    private $total_custom_values;

    private $total_tax_map;

    private $sub_total;

    private $gross_sub_total;

    private $precision;

    /**
     * Constructs the object with Invoice and Settings object.
     *
     * @param      Invoice  $invoice   The invoice
     */
    public function __construct($invoice)
    {
        $this->invoice = $invoice;

        if ($this->invoice->client) {
            $this->precision = $this->invoice->client->currency()->precision;
        } else {
            $this->precision = $this->invoice->vendor->currency()->precision;
        }

        $this->tax_map = new Collection;
    }

    public function build()
    {
        $this->calculateLineItems()
             ->calculateDiscount()
             ->calculateInvoiceTaxes()
             ->calculateCustomValues()
             ->setTaxMap()
             ->calculateTotals()
             ->calculateBalance()
             ->calculatePartial();

        return $this;
    }

    private function calculateLineItems()
    {
        $this->invoice_items = new InvoiceItemSum($this->invoice);
        $this->invoice_items->process();
        $this->invoice->line_items = $this->invoice_items->getLineItems();
        $this->total = $this->invoice_items->getSubTotal();
        $this->setSubTotal($this->invoice_items->getSubTotal());
        $this->setGrossSubTotal($this->invoice_items->getGrossSubTotal());

        return $this;
    }

    private function calculateDiscount()
    {
        $this->total_discount = $this->discount($this->invoice_items->getSubTotal());

        $this->total -= $this->total_discount;

        return $this;
    }

    private function calculateCustomValues()
    {

        $this->total_custom_values += $this->valuer($this->invoice->custom_surcharge1);

        $this->total_custom_values += $this->valuer($this->invoice->custom_surcharge2);

        $this->total_custom_values += $this->valuer($this->invoice->custom_surcharge3);

        $this->total_custom_values += $this->valuer($this->invoice->custom_surcharge4);

        $this->total += $this->total_custom_values;

        return $this;
    }

    private function calculateInvoiceTaxes()
    {
        if (is_string($this->invoice->tax_name1) && strlen($this->invoice->tax_name1) > 1) {
            $tax = $this->taxer($this->total, $this->invoice->tax_rate1);
            $tax += $this->getSurchargeTaxTotalForKey($this->invoice->tax_name1, $this->invoice->tax_rate1);

            $this->total_taxes += $tax;
            $this->total_tax_map[] = ['name' => $this->invoice->tax_name1.' '.floatval($this->invoice->tax_rate1).'%', 'total' => $tax];
        }

        if (is_string($this->invoice->tax_name2) && strlen($this->invoice->tax_name2) > 1) {
            $tax = $this->taxer($this->total, $this->invoice->tax_rate2);
            $tax += $this->getSurchargeTaxTotalForKey($this->invoice->tax_name2, $this->invoice->tax_rate2);

            $this->total_taxes += $tax;
            $this->total_tax_map[] = ['name' => $this->invoice->tax_name2.' '.floatval($this->invoice->tax_rate2).'%', 'total' => $tax];
        }

        if (is_string($this->invoice->tax_name3) && strlen($this->invoice->tax_name3) > 1) {
            $tax = $this->taxer($this->total, $this->invoice->tax_rate3);
            $tax += $this->getSurchargeTaxTotalForKey($this->invoice->tax_name3, $this->invoice->tax_rate3);

            $this->total_taxes += $tax;
            $this->total_tax_map[] = ['name' => $this->invoice->tax_name3.' '.floatval($this->invoice->tax_rate3).'%', 'total' => $tax];
        }

        return $this;
    }

    /**
     * Calculates the balance.
     *
     * @return     self  The balance.
     */
    private function calculateBalance()
    {

        $this->setCalculatedAttributes();

        return $this;
    }

    private function calculatePartial()
    {
        if (! isset($this->invoice->id) && isset($this->invoice->partial)) {
            $this->invoice->partial = max(0, min($this->formatValue($this->invoice->partial, 2), $this->invoice->balance));
        }

        return $this;
    }

    private function calculateTotals()
    {
        $this->total += $this->total_taxes;

        return $this;
    }

    /**
     * Allow us to get the entity without persisting it
     * @return Invoice the temp
     */
    public function getTempEntity()
    {
        $this->setCalculatedAttributes();

        return $this->invoice;
    }

    public function getInvoice()
    {
        //Build invoice values here and return Invoice
        $this->setCalculatedAttributes();
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    public function getQuote()
    {
        $this->setCalculatedAttributes();
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    public function getCredit()
    {
        $this->setCalculatedAttributes();
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    public function getPurchaseOrder()
    {
        $this->setCalculatedAttributes();
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    public function getRecurringInvoice()
    {
        $this->invoice->amount = $this->formatValue($this->getTotal(), $this->precision);
        $this->invoice->total_taxes = $this->getTotalTaxes();
        $this->invoice->balance = $this->formatValue($this->getTotal(), $this->precision);

        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    /**
     * Build $this->invoice variables after
     * calculations have been performed.
     */
    private function setCalculatedAttributes()
    {
        /* If amount != balance then some money has been paid on the invoice, need to subtract this difference from the total to set the new balance */

        if ($this->invoice->status_id != Invoice::STATUS_DRAFT) {
            if ($this->invoice->amount != $this->invoice->balance) {
                $paid_to_date = $this->invoice->amount - $this->invoice->balance;

                $this->invoice->balance = $this->formatValue($this->getTotal(), $this->precision) - $paid_to_date;
            } else {
                $this->invoice->balance = $this->formatValue($this->getTotal(), $this->precision);
            }
        }
        /* Set new calculated total */
        $this->invoice->amount = $this->formatValue($this->getTotal(), $this->precision);

        $this->invoice->total_taxes = $this->getTotalTaxes();

        return $this;
    }

    public function getSubTotal()
    {
        return $this->sub_total;
    }

    public function setSubTotal($value)
    {
        $this->sub_total = $value;

        return $this;
    }

    public function getGrossSubTotal()
    {
        return $this->gross_sub_total;
    }

    public function setGrossSubTotal($value)
    {
        $this->gross_sub_total = $value;

        return $this;
    }

    public function getTotalDiscount()
    {
        return $this->total_discount;
    }

    public function getTotalTaxes()
    {
        return $this->total_taxes;
    }

    public function getTotalTaxMap()
    {
        return $this->total_tax_map;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function setTaxMap()
    {
        if ($this->invoice->is_amount_discount == true) {
            $this->invoice_items->calcTaxesWithAmountDiscount();
        }

        $this->tax_map = collect();

        $keys = $this->invoice_items->getGroupedTaxes()->pluck('key')->unique();

        $values = $this->invoice_items->getGroupedTaxes();

        foreach ($keys as $key) {
            $tax_name = $values->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->pluck('tax_name')->first();

            $total_line_tax = $values->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->sum('total');

            //$total_line_tax -= $this->discount($total_line_tax);

            $this->tax_map[] = ['name' => $tax_name, 'total' => $total_line_tax];

            $this->total_taxes += $total_line_tax;
        }

        return $this;
    }

    private function getSurchargeTaxTotalForKey($key, $rate)
    {
        $tax_component = 0;

        if ($this->invoice->custom_surcharge_tax1) {
            $tax_component += round($this->invoice->custom_surcharge1 * ($rate / 100), 2);
        }

        if ($this->invoice->custom_surcharge_tax2) {
            $tax_component += round($this->invoice->custom_surcharge2 * ($rate / 100), 2);
        }

        if ($this->invoice->custom_surcharge_tax3) {
            $tax_component += round($this->invoice->custom_surcharge3 * ($rate / 100), 2);
        }

        if ($this->invoice->custom_surcharge_tax4) {
            $tax_component += round($this->invoice->custom_surcharge4 * ($rate / 100), 2);
        }

        return $tax_component;
    }

    public function getTaxMap()
    {
        return $this->tax_map;
    }

    public function getBalance()
    {
        return $this->invoice->balance;
    }

    public function getItemTotalTaxes()
    {
        return $this->getTotalTaxes();
    }

    public function purgeTaxes()
    {
        $this->tax_rate1 = 0;
        $this->tax_name1 = '';

        $this->tax_rate2 = 0;
        $this->tax_name2 = '';

        $this->tax_rate3 = 0;
        $this->tax_name3 = '';

        $this->discount = 0;

        $line_items = collect($this->invoice->line_items);

        $items = $line_items->map(function ($item) {
            $item->tax_rate1 = 0;
            $item->tax_rate2 = 0;
            $item->tax_rate3 = 0;
            $item->tax_name1 = '';
            $item->tax_name2 = '';
            $item->tax_name3 = '';
            $item->discount = 0;

            return $item;
        });

        $this->invoice->line_items = $items->toArray();

        $this->build();

        return $this;
    }
}
