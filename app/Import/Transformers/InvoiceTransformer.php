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
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|Item
     */
    public function transform($data)
    {
        return [
            'company_id' => $this->maps['company']->id,
            'number' => $this->getString($data, 'invoice.number'),
            'user_id' => $this->getString($data, 'invoice.user_id'),
            'amount' => $this->getFloat($data, 'invoice.amount'),
            'balance' => $this->getFloat($data, 'invoice.balance'),
            'client_id' => $this->getClient($this->getString($data, 'client.name'), $this->getString($data, 'client.email')),
            'discount' => $this->getFloat($data, 'invoice.discount'),
            'po_number' => $this->getString($data, 'invoice.po_number'),
            'date' => $this->getString($data, 'invoice.date'),
            'due_date' => $this->getString($data, 'invoice.due_date'),
            'terms' => $this->getString($data, 'invoice.terms'),
            'public_notes' => $this->getString($data, 'invoice.public_notes'),
            'is_sent' => $this->getString($data, 'invoice.is_sent'),
            'private_notes' => $this->getString($data, 'invoice.private_notes'),
            'tax_name1' => $this->getString($data, 'invoice.tax_name1'),
            'tax_rate1' => $this->getFloatWithSamePrecision($data, 'invoice.tax_rate1'),
            'tax_name2' => $this->getString($data, 'invoice.tax_name2'),
            'tax_rate2' => $this->getFloatWithSamePrecision($data, 'invoice.tax_rate2'),
            'tax_name3' => $this->getString($data, 'invoice.tax_name3'),
            'tax_rate3' => $this->getFloatWithSamePrecision($data, 'invoice.tax_rate3'),
            'custom_value1' => $this->getString($data, 'invoice.custom_value1'),
            'custom_value2' => $this->getString($data, 'invoice.custom_value2'),
            'custom_value3' => $this->getString($data, 'invoice.custom_value3'),
            'custom_value4' => $this->getString($data, 'invoice.custom_value4'),
            'footer' => $this->getString($data, 'invoice.footer'),
            'partial' => $this->getFloat($data, 'invoice.partial'),
            'partial_due_date' => $this->getString($data, 'invoice.partial_due_date'),
            'custom_surcharge1' => $this->getString($data, 'invoice.custom_surcharge1'),
            'custom_surcharge2' => $this->getString($data, 'invoice.custom_surcharge2'),
            'custom_surcharge3' => $this->getString($data, 'invoice.custom_surcharge3'),
            'custom_surcharge4' => $this->getString($data, 'invoice.custom_surcharge4'),
            'exchange_rate' => $this->getString($data, 'invoice.exchange_rate'),
        ];
    }
}
