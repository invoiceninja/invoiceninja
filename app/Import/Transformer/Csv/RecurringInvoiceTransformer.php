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

namespace App\Import\Transformer\Csv;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use App\Models\RecurringInvoice;
use App\Utils\Traits\CleanLineItems;

/**
 * Class RecurringInvoiceTransformer.
 */
class RecurringInvoiceTransformer extends BaseTransformer
{
    use CleanLineItems;

    /**
     * @param $data
     *
     * @return bool|array
     */
    public function transform($line_items_data)
    {
        if (!empty($line_items_data) && is_array(reset($line_items_data))) {
            $invoice_data = reset($line_items_data);
        } else {
            $invoice_data = $line_items_data;
            $line_items_data = [$invoice_data];
        }

        if (isset($invoice_data['invoice.number']) && $this->hasRecurringInvoice($invoice_data['invoice.number'])) {
            throw new ImportException('Invoice number already exists');
        }

        $invoiceStatusMap = [
            'sent' => RecurringInvoice::STATUS_ACTIVE,
            'active' => RecurringInvoice::STATUS_ACTIVE,
            'draft' => RecurringInvoice::STATUS_DRAFT,
        ];

        $status = strtolower($this->getString($invoice_data, 'invoice.status'));
        $statusId = $invoiceStatusMap[$status] ?? RecurringInvoice::STATUS_DRAFT;

        if ($status === '' && array_key_exists('invoice.is_sent', $invoice_data)) {
            $statusId = $this->toBoolean($invoice_data['invoice.is_sent'])
                ? RecurringInvoice::STATUS_ACTIVE
                : RecurringInvoice::STATUS_DRAFT;
        }

        $transformed = [
            'company_id' => $this->company->id,
            'number' => $this->getString($invoice_data, 'invoice.number', null),
            'user_id' => $this->getString($invoice_data, 'invoice.user_id'),
            'amount' => ($amount = $this->getFloat(
                $invoice_data,
                'invoice.amount'
            )),
            'balance' => isset($invoice_data['invoice.balance'])
                ? $this->getFloat($invoice_data, 'invoice.balance')
                : $amount,
            'client_id' => $this->getClient(
                $this->getString($invoice_data, 'client.name'),
                $this->getString($invoice_data, 'client.email')
            ),
            'discount' => $this->getFloat($invoice_data, 'invoice.discount'),
            'po_number' => $this->getString($invoice_data, 'invoice.po_number'),
            'date' => isset($invoice_data['invoice.date'])
                ? $this->parseDate($invoice_data['invoice.date'])
                : now()->format('Y-m-d'),
            'next_send_date' => isset($invoice_data['invoice.next_send_date'])
                ? $this->parseDate($invoice_data['invoice.next_send_date'])
                : now()->format('Y-m-d'),
            'next_send_date_client' => isset($invoice_data['invoice.next_send_date'])
                ? $this->parseDate($invoice_data['invoice.next_send_date'])
                : now()->format('Y-m-d'),
            'due_date' => isset($invoice_data['invoice.due_date']) ? $this->parseDate($invoice_data['invoice.due_date']) : null,
            'terms' => $this->getString($invoice_data, 'invoice.terms'),
            'due_date_days' => 'terms',
            'public_notes' => $this->getString(
                $invoice_data,
                'invoice.public_notes'
            ),
            'private_notes' => $this->getString(
                $invoice_data,
                'invoice.private_notes'
            ),
            'tax_name1' => $this->getString($invoice_data, 'invoice.tax_name1'),
            'tax_rate1' => $this->getFloat($invoice_data, 'invoice.tax_rate1'),
            'tax_name2' => $this->getString($invoice_data, 'invoice.tax_name2'),
            'tax_rate2' => $this->getFloat($invoice_data, 'invoice.tax_rate2'),
            'tax_name3' => $this->getString($invoice_data, 'invoice.tax_name3'),
            'tax_rate3' => $this->getFloat($invoice_data, 'invoice.tax_rate3'),
            'custom_value1' => $this->getCustomFieldValue('invoice1', $this->getString(
                $invoice_data,
                'invoice.custom_value1'
            )),
            'custom_value2' => $this->getCustomFieldValue('invoice2', $this->getString(
                $invoice_data,
                'invoice.custom_value2'
            )),
            'custom_value3' => $this->getCustomFieldValue('invoice3', $this->getString(
                $invoice_data,
                'invoice.custom_value3'
            )),
            'custom_value4' => $this->getCustomFieldValue('invoice4', $this->getString(
                $invoice_data,
                'invoice.custom_value4'
            )),
            'footer' => $this->getString($invoice_data, 'invoice.footer'),
            'partial' => $this->getFloat($invoice_data, 'invoice.partial') > 0 ? $this->getFloat($invoice_data, 'invoice.partial') : null,
            'partial_due_date' => isset($invoice_data['invoice.partial_due_date']) ? $this->parseDate($invoice_data['invoice.partial_due_date']) : null,
            'custom_surcharge1' => $this->getString(
                $invoice_data,
                'invoice.custom_surcharge1'
            ),
            'custom_surcharge2' => $this->getString(
                $invoice_data,
                'invoice.custom_surcharge2'
            ),
            'custom_surcharge3' => $this->getString(
                $invoice_data,
                'invoice.custom_surcharge3'
            ),
            'custom_surcharge4' => $this->getString(
                $invoice_data,
                'invoice.custom_surcharge4'
            ),
            'is_amount_discount' => filter_var(
                $this->getString($invoice_data, 'invoice.is_amount_discount'),
                FILTER_VALIDATE_BOOLEAN
            ),
            'status_id' => $statusId,
            'auto_bill' => $this->getAutoBillFlag(
                $this->getString($invoice_data, 'invoice.auto_bill')
            ),
            'frequency_id' => $this->getFrequency(
                $invoice_data['invoice.frequency_id'] ?? 'monthly'
            ),
            'remaining_cycles' => $this->getRemainingCycles(
                $invoice_data['invoice.remaining_cycles'] ?? -1
            ),
            // 'archived' => $status === 'archived',
        ];

        if (array_key_exists('invoice.exchange_rate', $invoice_data)) {
            $transformed['exchange_rate'] = $this->getFloatOrOne($invoice_data, 'invoice.exchange_rate');
        }

        if (array_key_exists('invoice.uses_inclusive_taxes', $invoice_data)) {
            $transformed['uses_inclusive_taxes'] = $this->toBoolean($invoice_data['invoice.uses_inclusive_taxes']);
        }

        /* If we can't find the client, then lets try and create a client */
        if (! $transformed['client_id']) {
            $client_transformer = new ClientTransformer($this->company);

            $transformed['client'] = $client_transformer->transform(
                $invoice_data
            );
        }

        $line_items = [];
        foreach ($line_items_data as $record) {
            $line_items[] = [
                'quantity' => $this->getFloat($record, 'item.quantity'),
                'cost' => $this->getFloat($record, 'item.cost'),
                'product_cost' => $this->getFloat($record, 'item.product_cost'),
                'product_key' => $this->getString($record, 'item.product_key'),
                'notes' => $this->getString($record, 'item.notes'),
                'discount' => $this->getFloat($record, 'item.discount'),
                'is_amount_discount' => filter_var(
                    $this->getString($record, 'item.is_amount_discount'),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'tax_name1' => $this->getString($record, 'item.tax_name1'),
                'tax_rate1' => $this->getFloat($record, 'item.tax_rate1'),
                'tax_name2' => $this->getString($record, 'item.tax_name2'),
                'tax_rate2' => $this->getFloat($record, 'item.tax_rate2'),
                'tax_name3' => $this->getString($record, 'item.tax_name3'),
                'tax_rate3' => $this->getFloat($record, 'item.tax_rate3'),
                'custom_value1' => $this->getCustomFieldValue('product1', $this->getString(
                    $record,
                    'item.custom_value1'
                )),
                'custom_value2' => $this->getCustomFieldValue('product2', $this->getString(
                    $record,
                    'item.custom_value2'
                )),
                'custom_value3' => $this->getCustomFieldValue('product3', $this->getString(
                    $record,
                    'item.custom_value3'
                )),
                'custom_value4' => $this->getCustomFieldValue('product4', $this->getString(
                    $record,
                    'item.custom_value4'
                )),
                'type_id' => $this->getInvoiceTypeId($record, 'item.type_id'),
                'tax_id' => $this->getString($record, 'item.tax_id'),
            ];
        }

        $transformed['line_items'] = $this->cleanItems($line_items);

        return $transformed;
    }
}
