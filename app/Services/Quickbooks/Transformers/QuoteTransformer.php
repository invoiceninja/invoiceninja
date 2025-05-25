<?php

/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

use App\DataMapper\InvoiceItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\Quote;
use App\Models\Product;
use App\DataMapper\QuoteItem;
use App\Models\TaxRate;

/**
 * Class QuoteTransformer.
 */
class QuoteTransformer extends BaseTransformer
{
    public function qbToNinja(mixed $qb_data)
    {
        return $this->transform($qb_data);
    }

    public function ninjaToQb()
    {
    }

    public function transform($qb_data)
    {
        $client_id = $this->getClientId(data_get($qb_data, 'CustomerRef', null));
        $tax_array = $this->calculateTotalTax($qb_data);

        return $client_id ? [
            'id' => data_get($qb_data, 'Id', false),
            'client_id' => $client_id,
            'number' => data_get($qb_data, 'DocNumber', false),
            'date' => data_get($qb_data, 'TxnDate', now()->format('Y-m-d')),
            'private_notes' => data_get($qb_data, 'PrivateNote', ''),
            'public_notes' => data_get($qb_data, 'CustomerMemo', false),
            'due_date' => data_get($qb_data, 'ExpirationDate', null),
            'line_items' => $this->getLineItems($qb_data, $tax_array),
            'status_id' => Quote::STATUS_SENT,
            'custom_surcharge1' => $this->checkIfDiscountAfterTax($qb_data),
            'balance' => data_get($qb_data, 'Balance', 0),

        ] : false;
    }

    private function checkIfDiscountAfterTax($qb_data)
    {

        if (data_get($qb_data, 'ApplyTaxAfterDiscount') == 'true') {
            return 0;
        }

        foreach (data_get($qb_data, 'Line', []) as $line) {

            if (data_get($line, 'DetailType') == 'DiscountLineDetail') {

                if (!isset($this->company->custom_fields->surcharge1)) {
                    $this->company->custom_fields->surcharge1 = ctrans('texts.discount');
                    $this->company->save();
                }

                return (float)data_get($line, 'Amount', 0) * -1;
            }
        }

        return 0;
    }

    private function calculateTotalTax($qb_data)
    {
        $total_tax = data_get($qb_data, 'TxnTaxDetail.TotalTax', false);

        $tax_rate = 0;
        $tax_name = '';

        if ($total_tax == "0") {
            return [$tax_rate, $tax_name];
        }

        $taxLines = data_get($qb_data, 'TxnTaxDetail.TaxLine', []) ?? [];

        if (!empty($taxLines) && !isset($taxLines[0])) {
            $taxLines = [$taxLines];
        }

        $totalTaxRate = 0;

        foreach ($taxLines as $taxLine) {
            $taxRate = data_get($taxLine, 'TaxLineDetail.TaxPercent', 0);
            $totalTaxRate += $taxRate;
        }


        if ($totalTaxRate > 0) {
            $formattedTaxRate = rtrim(rtrim(number_format($totalTaxRate, 6), '0'), '.');
            $formattedTaxRate = trim($formattedTaxRate);

            $tr = \App\Models\TaxRate::firstOrNew(
                [
                'company_id' => $this->company->id,
                'rate' => $formattedTaxRate,
                ],
                [
                'name' => "Sales Tax [{$formattedTaxRate}]",
                'rate' => $formattedTaxRate,
                ]
            );
            $tr->company_id = $this->company->id;
            $tr->user_id = $this->company->owner()->id;
            $tr->save();

            $tax_rate = $tr->rate;
            $tax_name = $tr->name;
        }

        return [$tax_rate, $tax_name];

    }


    // private function getPayments(mixed $qb_data)
    // {
    //     $payments = [];

    //     $qb_payments = data_get($qb_data, 'LinkedTxn', false) ?? [];

    //     if (!empty($qb_payments) && !isset($qb_payments[0])) {
    //         $qb_payments = [$qb_payments];
    //     }

    //     foreach ($qb_payments as $payment) {
    //         if (data_get($payment, 'TxnType', false) == 'Payment') {
    //             $payments[] = data_get($payment, 'TxnId', false);
    //         }
    //     }

    //     return $payments;

    // }

    private function getLineItems(mixed $qb_data, array $tax_array)
    {
        $qb_items = data_get($qb_data, 'Line', []);

        $include_discount = data_get($qb_data, 'ApplyTaxAfterDiscount', 'true');

        $items = [];

        if (!empty($qb_items) && !isset($qb_items[0])) {

            //handle weird statement charges
            $tax_rate = (float)data_get($qb_data, 'TxnTaxDetail.TaxLine.TaxLineDetail.TaxPercent', 0);
            $tax_name = $tax_rate > 0 ? "Sales Tax [{$tax_rate}]" : '';

            $item = new InvoiceItem();
            $item->product_key = '';
            $item->notes = 'Recurring Charge';
            $item->quantity = 1;
            $item->cost = (float)data_get($qb_items, 'Amount', 0);
            $item->discount = 0;
            $item->is_amount_discount = false;
            $item->type_id = '1';
            $item->tax_id = '1';
            $item->tax_rate1 = (float)$tax_rate;
            $item->tax_name1 = $tax_name;

            $items[] = (object)$item;

            return $items;
        }

        foreach ($qb_items as $qb_item) {

            $taxCodeRef = data_get($qb_item, 'TaxCodeRef', data_get($qb_item, 'SalesItemLineDetail.TaxCodeRef', 'TAX'));

            if (data_get($qb_item, 'DetailType') == 'SalesItemLineDetail') {
                $item = new InvoiceItem();
                $item->product_key = data_get($qb_item, 'SalesItemLineDetail.ItemRef.name', '');
                $item->notes = data_get($qb_item, 'Description', '');
                $item->quantity = (float)(data_get($qb_item, 'SalesItemLineDetail.Qty') ?? 1);
                $item->cost = (float)(data_get($qb_item, 'SalesItemLineDetail.UnitPrice') ?? data_get($qb_item, 'SalesItemLineDetail.MarkupInfo.Value', 0));
                $item->discount = (float)data_get($item, 'DiscountRate', data_get($qb_item, 'DiscountAmount', 0));
                $item->is_amount_discount = data_get($qb_item, 'DiscountAmount', 0) > 0 ? true : false;
                $item->type_id = stripos(data_get($qb_item, 'ItemAccountRef.name') ?? '', 'Service') !== false ? '2' : '1';
                $item->tax_id = $taxCodeRef == 'NON' ? Product::PRODUCT_TYPE_EXEMPT : $item->type_id;
                $item->tax_rate1 = (float)$taxCodeRef == 'NON' ? 0 : $tax_array[0];
                $item->tax_name1 = $taxCodeRef == 'NON' ? '' : $tax_array[1];

                $items[] = (object)$item;
            }

            if (data_get($qb_item, 'DetailType') == 'DiscountLineDetail' && $include_discount == 'true') {

                $item = new InvoiceItem();
                $item->product_key = ctrans('texts.discount');
                $item->notes = ctrans('texts.discount');
                $item->quantity = 1;
                $item->cost = (float)data_get($qb_item, 'Amount', 0) * -1;
                $item->discount = 0;
                $item->is_amount_discount = true;

                $item->tax_rate1 = (float)$include_discount == 'true' ? $tax_array[0] : 0;
                $item->tax_name1 = $include_discount == 'true' ? $tax_array[1] : '';

                $item->type_id = '1';
                $item->tax_id = Product::PRODUCT_TYPE_PHYSICAL;
                $items[] = (object)$item;

            }
        }

        return $items;

    }

}
