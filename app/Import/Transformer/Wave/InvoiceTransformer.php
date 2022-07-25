<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/clientninja/clientninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://clientninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Wave;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
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

        if (array_key_exists('Invoice Date', $invoice_data)) {
            $date_key = 'Invoice Date';
        } else {
            $date_key = 'Transaction Date';
        }

        if (array_key_exists('Customer Name', $invoice_data)) {
            $customer_key = 'Customer Name';
        } else {
            $customer_key = 'Customer';
        }

        $transformed = [
            'company_id'  => $this->company->id,
            'client_id'   => $this->getClient($customer_name = $this->getString($invoice_data, $customer_key), null),
            'number'      => $invoice_number = $this->getString($invoice_data, 'Invoice Number'),
            'date'        => date('Y-m-d', strtotime($invoice_data[$date_key])) ?: now()->format('Y-m-d'), //27-01-2022
            'currency_id' => $this->getCurrencyByCode($invoice_data, 'Currency'),
            'status_id'   => Invoice::STATUS_SENT,
            'due_date'	  => array_key_exists('Due Date', $invoice_data) ? date('Y-m-d', strtotime($invoice_data['Due Date'])) : null,
        ];

        $line_items = [];
        $payments = [];
        foreach ($line_items_data as $record) {
            if (array_key_exists('Account Type', $record) && $record['Account Type'] === 'Income') {
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
            } elseif (array_key_exists('Account Type', $record) && $record['Account Type'] === 'System Receivable Invoice') {
                // This is a payment
                $payments[] = [
                    'date'   => date('Y-m-d', strtotime($invoice_data[$date_key])),
                    'amount' => $this->getFloat($record, 'Amount (One column)'),
                ];
            } else {
                //could be a generate invoices.csv file

                $calculated_tax_rate = 0;

                if ($this->getFloat($record, 'Invoice Tax Total') != 0 && $this->getFloat($record, 'Invoice Total') != 0) {
                    $calculated_tax_rate = round($this->getFloat($record, 'Invoice Tax Total') / $this->getFloat($record, 'Invoice Total') * 100, 2);
                }

                $line_items[] = [
                    'notes'     => 'Imported Invoice',
                    'cost'      => $this->getFloat($record, 'Invoice Total'),
                    'tax_name1' => 'Tax',
                    'tax_rate1' => $calculated_tax_rate,
                    'quantity' => 1,
                ];

                if (array_key_exists('Invoice Paid', $record) && $record['Invoice Paid'] > 0) {
                    $payments[] = [
                        'date'   => date('Y-m-d', strtotime($record['Last Payment Date'])),
                        'amount' => $this->getFloat($record, 'Invoice Paid'),
                    ];
                }
            }
        }

        $transformed['line_items'] = $line_items;
        $transformed['payments'] = $payments;

        return $transformed;
    }
}
