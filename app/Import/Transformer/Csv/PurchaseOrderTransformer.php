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
use App\Models\PurchaseOrder;
use App\Utils\Traits\CleanLineItems;

/**
 * Class PurchaseOrderTransformer.
 */
class PurchaseOrderTransformer extends BaseTransformer
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
            $purchase_order_data = reset($line_items_data);
        } else {
            $purchase_order_data = $line_items_data;
            $line_items_data = [$purchase_order_data];
        }

        if (isset($purchase_order_data['purchase_order.number']) && $this->hasPurchaseOrder($purchase_order_data['purchase_order.number'])) {
            throw new ImportException('Purchase order number already exists');
        }

        $purchaseOrderStatusMap = [
            'draft' => PurchaseOrder::STATUS_DRAFT,
            'sent' => PurchaseOrder::STATUS_SENT,
            'accepted' => PurchaseOrder::STATUS_ACCEPTED,
            'received' => PurchaseOrder::STATUS_RECEIVED,
            'cancelled' => PurchaseOrder::STATUS_CANCELLED,
        ];

        $transformed = [
            'company_id' => $this->company->id,
            'number' => $this->getString($purchase_order_data, 'purchase_order.number'),
            'user_id' => $this->getString($purchase_order_data, 'purchase_order.user_id'),
            'amount' => ($amount = $this->getFloat(
                $purchase_order_data,
                'purchase_order.amount'
            )),
            'balance' => isset($purchase_order_data['purchase_order.balance'])
                ? $this->getFloat($purchase_order_data, 'purchase_order.balance')
                : $amount,
            'vendor_id' => $this->getVendorIdOrCreate(
                $this->getString($purchase_order_data, 'vendor.name')
            ),
            'discount' => $this->getFloat($purchase_order_data, 'purchase_order.discount'),
            'po_number' => $this->getString($purchase_order_data, 'purchase_order.po_number'),
            'date' => isset($purchase_order_data['purchase_order.date'])
                ? $this->parseDate($purchase_order_data['purchase_order.date'])
                : now()->format('Y-m-d'),
            'due_date' => isset($purchase_order_data['purchase_order.due_date'])
                ? $this->parseDate($purchase_order_data['purchase_order.due_date'])
                : null,
            'terms' => $this->getString($purchase_order_data, 'purchase_order.terms'),
            'public_notes' => $this->getString(
                $purchase_order_data,
                'purchase_order.public_notes'
            ),
            'private_notes' => $this->getString(
                $purchase_order_data,
                'purchase_order.private_notes'
            ),
            'tax_name1' => $this->getString($purchase_order_data, 'purchase_order.tax_name1'),
            'tax_rate1' => $this->getFloat($purchase_order_data, 'purchase_order.tax_rate1'),
            'tax_name2' => $this->getString($purchase_order_data, 'purchase_order.tax_name2'),
            'tax_rate2' => $this->getFloat($purchase_order_data, 'purchase_order.tax_rate2'),
            'tax_name3' => $this->getString($purchase_order_data, 'purchase_order.tax_name3'),
            'tax_rate3' => $this->getFloat($purchase_order_data, 'purchase_order.tax_rate3'),
            'custom_value1' => $this->getString(
                $purchase_order_data,
                'purchase_order.custom_value1'
            ),
            'custom_value2' => $this->getString(
                $purchase_order_data,
                'purchase_order.custom_value2'
            ),
            'custom_value3' => $this->getString(
                $purchase_order_data,
                'purchase_order.custom_value3'
            ),
            'custom_value4' => $this->getString(
                $purchase_order_data,
                'purchase_order.custom_value4'
            ),
            'footer' => $this->getString($purchase_order_data, 'purchase_order.footer'),
            'partial' => $this->getFloat($purchase_order_data, 'purchase_order.partial'),
            'partial_due_date' => isset($purchase_order_data['purchase_order.partial_due_date']) ? $this->parseDate($purchase_order_data['purchase_order.partial_due_date']) : null,
            'custom_surcharge1' => $this->getString(
                $purchase_order_data,
                'purchase_order.custom_surcharge1'
            ),
            'custom_surcharge2' => $this->getString(
                $purchase_order_data,
                'purchase_order.custom_surcharge2'
            ),
            'custom_surcharge3' => $this->getString(
                $purchase_order_data,
                'purchase_order.custom_surcharge3'
            ),
            'custom_surcharge4' => $this->getString(
                $purchase_order_data,
                'purchase_order.custom_surcharge4'
            ),
            'exchange_rate' => $this->getFloatOrOne(
                $purchase_order_data,
                'purchase_order.exchange_rate'
            ),
            'status_id' => $purchaseOrderStatusMap[
                    strtolower(
                        $this->getString($purchase_order_data, 'purchase_order.status')
                    )
                ] ?? PurchaseOrder::STATUS_SENT,
        ];

        if (isset($purchase_order_data['purchase_order.currency_id'])) {
            $currency_id = $this->getCurrencyByCode($purchase_order_data['purchase_order.currency_id']);
            if ($currency_id) {
                $transformed['currency_id'] = $currency_id;
            }
        }

        $line_items = [];
        foreach ($line_items_data as $record) {
            $line_items[] = [
                'quantity' => $this->getFloat($record, 'item.quantity'),
                'cost' => $this->getFloat($record, 'item.cost'),
                'product_key' => $this->getString($record, 'item.product_key'),
                'notes' => $this->getString($record, 'item.notes'),
                'discount' => $this->getFloat($record, 'item.discount'),
                'is_amount_discount' => filter_var(
                    $this->getString($record, 'item.is_amount_discount'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
                'tax_name1' => $this->getString($record, 'item.tax_name1'),
                'tax_rate1' => $this->getFloat($record, 'item.tax_rate1'),
                'tax_name2' => $this->getString($record, 'item.tax_name2'),
                'tax_rate2' => $this->getFloat($record, 'item.tax_rate2'),
                'tax_name3' => $this->getString($record, 'item.tax_name3'),
                'tax_rate3' => $this->getFloat($record, 'item.tax_rate3'),
                'custom_value1' => $this->getString(
                    $record,
                    'item.custom_value1'
                ),
                'custom_value2' => $this->getString(
                    $record,
                    'item.custom_value2'
                ),
                'custom_value3' => $this->getString(
                    $record,
                    'item.custom_value3'
                ),
                'custom_value4' => $this->getString(
                    $record,
                    'item.custom_value4'
                ),
                'type_id' => '1',
            ];
        }
        $transformed['line_items'] = $this->cleanItems($line_items);

        return $transformed;
    }
}
