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

namespace App\Import\Transformers;

/**
 * Class InvoiceItemTransformer.
 */
class InvoiceItemTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        return [
            'quantity' => $this->getFloat($data, 'item.quantity'),
            'cost' => $this->getFloat($data, 'item.cost'),
            'product_key' => $this->getString($data, 'item.product_key'),
            'notes' => $this->getString($data, 'item.notes'),
            'discount' => $this->getFloat($data, 'item.discount'),
            'is_amount_discount' => $this->getString($data, 'item.is_amount_discount'),
            'tax_name1' => $this->getString($data, 'item.tax_name1'),
            'tax_rate1' => $this->getFloat($data, 'item.tax_rate1'),
            'tax_name2' => $this->getString($data, 'item.tax_name2'),
            'tax_rate2' => $this->getFloat($data, 'item.tax_rate2'),
            'tax_name3' => $this->getString($data, 'item.tax_name3'),
            'tax_rate3' => $this->getFloat($data, 'item.tax_rate3'),
            'custom_value1' => $this->getString($data, 'item.custom_value1'),
            'custom_value2' => $this->getString($data, 'item.custom_value2'),
            'custom_value3' => $this->getString($data, 'item.custom_value3'),
            'custom_value4' => $this->getString($data, 'item.custom_value4'),
            'type_id' => $this->getInvoiceTypeId($data, 'item.type_id'),
        ];
    }
}
