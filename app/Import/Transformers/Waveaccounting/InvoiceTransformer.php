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

namespace App\Import\Transformers\Waveaccounting;

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

        $transformed = [
            'company_id'  => $this->maps['company']->id,
            'client_id'   => $this->getClient($customer_name = $this->getString($invoice_data, 'Customer'), null),
            'number'      => $invoice_number = $this->getString($invoice_data, 'Invoice Number'),
            'date'        => date('Y-m-d', strtotime($invoice_data['Transaction Date'])) ?: now()->format('Y-m-d'), //27-01-2022
            // 'date'        => isset( $invoice_data['Invoice Date'] ) ? date( 'Y-m-d', strtotime( $invoice_data['Transaction Date'] ) ) : null,
            'currency_id' => $this->getCurrencyByCode($invoice_data, 'Currency'),
            'status_id'   => Invoice::STATUS_SENT,
        ];

        $line_items = [];
        $payments = [];
        foreach ($line_items_data as $record) {
            if ($record['Account Type'] === 'Income') {
                $description = $this->getString($record, 'Transaction Line Description');

                // Remove duplicate data from description
                if (substr($description, 0, strlen($customer_name) + 3) === $customer_name.' - ') {
                    $description = substr($description, strlen($customer_name) + 3);
                }

                if (substr($description, 0, strlen($invoice_number) + 3) === $invoice_number.' - ') {
                    $description = substr($description, strlen($invoice_number) + 3);
                }

                $line_items[] = [
                    'notes'     => $description,
                    'cost'      => $this->getFloat($record, 'Amount Before Sales Tax'),
                    'tax_name1' => $this->getString($record, 'Sales Tax Name'),
                    'tax_rate1' => $this->getFloat($record, 'Sales Tax Amount'),

                    'quantity' => 1,
                ];
            } elseif ($record['Account Type'] === 'System Receivable Invoice') {
                // This is a payment
                $payments[] = [
                    'date'   => date('Y-m-d', strtotime($invoice_data['Transaction Date'])),
                    'amount' => $this->getFloat($record, 'Amount (One column)'),
                ];
            }
        }

        $transformed['line_items'] = $line_items;
        $transformed['payments'] = $payments;

        return $transformed;
    }
}
