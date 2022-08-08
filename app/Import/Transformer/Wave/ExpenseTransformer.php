<?php
/**
 * client Ninja (https://clientninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2022. client Ninja LLC (https://clientninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Wave;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;

/**
 * Class ExpenseTransformer.
 */
class ExpenseTransformer extends BaseTransformer
{
    /**
     * @param $line_items_data
     *
     * @return bool|array
     */
    public function transform($line_items_data)
    {
        $data = $line_items_data[0];

        $amount = 0;
        $total_tax = 0;
        $tax_rate = 0;

        foreach ($line_items_data as $record) {
            $amount += floatval($record['Amount (One column)']);
            $total_tax += floatval($record['Sales Tax Amount']);
        }

        $tax_rate = round(($total_tax / $amount) * 100, 3);

        $transformed = [
            'company_id'  => $this->company->id,
            'vendor_id'   => $this->getVendorIdOrCreate($this->getString($data, 'Vendor')),
            'number' 	  => $this->getString($data, 'Bill Number'),
            'public_notes'=> $this->getString($data, 'Notes / Memo'),
            'date'        => date('Y-m-d', strtotime($data['Transaction Date Added'])) ?: now()->format('Y-m-d'), //27-01-2022
            'currency_id' => $this->company->settings->currency_id,
            'category_id' => $this->getOrCreateExpenseCategry($data['Account Name']),
            'amount'	  => $amount,
            'tax_name1'   => $data['Sales Tax Name'],
            'tax_rate1'	  => $tax_rate,
        ];

        return $transformed;
    }
}
