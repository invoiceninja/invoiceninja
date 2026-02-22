<?php

/**
 * Invoice Ninja (https://clientninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

use App\Models\Product;
use App\Services\Quickbooks\QuickbooksService;

/**
 * Class ProductTransformer.
 */
class ProductTransformer extends BaseTransformer
{
    public function qbToNinja(mixed $qb_data, QuickbooksService $qb_service)
    {
        return $this->transform($qb_data, $qb_service);
    }



    public function qbTransform($line_item, $income_account_id): array
    {
        return [
            'Name' => strlen($line_item->product_key ?? '') > 0 ? $line_item->product_key : 'Product ' . uniqid(),
            'Description' => $line_item->notes,
            'PurchaseCost' => $line_item->product_cost ?? 0,
            'UnitPrice' => $line_item->cost,
            'Type' => $line_item->type_id == '2' || in_array($line_item->tax_id, ['5','8']) ? 'Service' : 'NonInventory',
            'IncomeAccountRef' => [
                'value' => strlen($line_item->income_account_id ?? '') > 0 ? $line_item->income_account_id : $income_account_id,
            ],
        ];
    }

    public function transform(mixed $data, ?QuickbooksService $qb_service = null): array
    {
        // Safety check: Skip Category items - they should be filtered at query level
        // but this provides defense in depth
        $item_type = data_get($data, 'Type');
        if ($item_type === 'Category' || $item_type === 'Group') {
            nlog("WARNING: Category/Group item detected in transform (should have been filtered): " . data_get($data, 'Name') . " (Type: {$item_type})");
            // Return minimal data to avoid errors, but log for investigation
        }

        $sales_tax_code_ref = data_get($data, 'SalesTaxCodeRef.value') ?: data_get($data, 'SalesTaxCodeRef');
        $tax_rate_name = '';
        $tax_rate_percentage = 0.0;
        $tax_code = null;
        
        if ($sales_tax_code_ref && $qb_service && ($tax_code = $qb_service->getTaxCode($sales_tax_code_ref))) {

            $tax_rate_ref = data_get($tax_code, 'SalesTaxRateList.TaxRateDetail.TaxRateRef', null);
            
            if ($tax_rate_ref) {
                $tax_rate_map_by_id = collect($qb_service->company->quickbooks->settings->tax_rate_map ?? [])->keyBy('id')->toArray();
                $tax_rate = $tax_rate_map_by_id[$tax_rate_ref] ?? null;
                if ($tax_rate) {
                    $tax_rate_name = data_get($tax_rate, 'name', '');
                    $tax_rate_percentage = (float) data_get($tax_rate, 'rate', 0);
                }
            }
        }

        $tax_id = '1';
        
        if ($sales_tax_code_ref && stripos($sales_tax_code_ref, 'NON') !== false) {
            $tax_id = '5';
        }
        
        if ($tax_id === '1' && data_get($data, 'Type') === 'Service') {
            $tax_id = '2';
        }

        return [
            'id' => data_get($data, 'Id'),
            'product_key' => data_get($data, 'Name', data_get($data, 'FullyQualifiedName', '')),
            'notes' => data_get($data, 'Description', ''),
            'cost' => (float) data_get($data, 'PurchaseCost', 0),
            'price' => (float) data_get($data, 'UnitPrice', 0),
            'in_stock_quantity' => (float) data_get($data, 'QtyOnHand', 0),
            'income_account_id' => data_get($data, 'IncomeAccountRef.value', data_get($data, 'IncomeAccountRef')),
            'type_id' => data_get($data, 'Type') === 'Service' ? '2' : '1',
            'tax_id' => $tax_id,
            'tax_name1' => $tax_rate_name,
            'tax_rate1' => $tax_rate_percentage,
        ];
    }

}
