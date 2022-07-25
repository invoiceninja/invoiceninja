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

namespace App\Import\Transformers\Zoho;

use App\Import\ImportException;
use App\Import\Transformers\BaseTransformer;
use App\Models\Invoice;

/**
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * @param $line_items_data
     *
     * @return bool|array
     */
    public function transform($line_items_data)
    {
        $invoice_data = reset($line_items_data);

        if ($this->hasInvoice($invoice_data['Invoice Number'])) {
            throw new ImportException('Invoice number already exists');
        }

        $invoiceStatusMap = [
            'sent'  => Invoice::STATUS_SENT,
            'draft' => Invoice::STATUS_DRAFT,
        ];

        $transformed = [
            'company_id'   => $this->maps['company']->id,
            'client_id'    => $this->getClient($this->getString($invoice_data, 'Customer ID'), null),
            'number'       => $this->getString($invoice_data, 'Invoice Number'),
            'date'         => isset($invoice_data['Invoice Date']) ? date('Y-m-d', strtotime($invoice_data['Invoice Date'])) : null,
            'due_date'     => isset($invoice_data['Due Date']) ? date('Y-m-d', strtotime($invoice_data['Due Date'])) : null,
            'po_number'    => $this->getString($invoice_data, 'PurchaseOrder'),
            'public_notes' => $this->getString($invoice_data, 'Notes'),
            'currency_id'  => $this->getCurrencyByCode($invoice_data, 'Currency'),
            'amount'       => $this->getFloat($invoice_data, 'Total'),
            'balance'      => $this->getFloat($invoice_data, 'Balance'),
            'status_id'    => $invoiceStatusMap[$status =
                    strtolower($this->getString($invoice_data, 'Invoice Status'))] ?? Invoice::STATUS_SENT,
            // 'viewed'       => $status === 'viewed',
        ];

        $line_items = [];
        foreach ($line_items_data as $record) {
            $line_items[] = [
                'product_key'        => $this->getString($record, 'Item Name'),
                'notes'              => $this->getString($record, 'Item Description'),
                'cost'               => round($this->getFloat($record, 'Item Price'), 2),
                'quantity'           => $this->getFloat($record, 'Quantity'),
                'discount'           => $this->getString($record, 'Discount Amount'),
                'is_amount_discount' => true,
            ];
        }
        $transformed['line_items'] = $line_items;

        if ($transformed['balance'] < $transformed['amount']) {
            $transformed['payments'] = [[
                'date'   => isset($invoice_data['Last Payment Date']) ? date('Y-m-d', strtotime($invoice_data['Invoice Date'])) : date('Y-m-d'),
                'amount' => $transformed['amount'] - $transformed['balance'],
            ]];
        }

        return $transformed;
    }
}
