<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\PdfMaker\Designs\Utilities;

trait BuildTableHeader
{
    /**
     * This method will help us decide either we show
     * one "tax rate" column in the table or 3 custom tax rates.
     * 
     * Logic below will help us calculate that & inject the result in the
     * global state of the $context (design state).
     * 
     * @return void
     */
    public function processTaxColumns(): void
    {
        if (in_array('$product.tax', $this->context['product-table-columns'])) {
            $line_items = collect($this->entity->line_items);

            $tax1 = $line_items->where('tax_name1', '<>', '')->where('type_id', 1)->count();
            $tax2 = $line_items->where('tax_name2', '<>', '')->where('type_id', 1)->count();
            $tax3 = $line_items->where('tax_name3', '<>', '')->where('type_id', 1)->count();
            $taxes = [];

            if ($tax1 > 0) {
                array_push($taxes, '$product.tax_rate1');
            }

            if ($tax2 > 0) {
                array_push($taxes, '$product.tax_rate2');
            }

            if ($tax3 > 0) {
                array_push($taxes, '$product.tax_rate3');
            }

            $key = array_search('$product.tax', $this->context['product-table-columns'], true);

            if ($key) {
                array_splice($this->context['product-table-columns'], $key, 1, $taxes);
            }
        }
    }

    /**
     * Calculates the remaining colspans.
     * 
     * @param int $taken 
     * @return int 
     */
    public function calculateColspan(int $taken): int
    {
        $total = (int) count($this->context['product-table-columns']);

        return (int)$total - $taken;
    }
}
