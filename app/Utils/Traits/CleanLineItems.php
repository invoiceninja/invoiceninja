<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\DataMapper\BaseSettings;
use App\DataMapper\InvoiceItem;

/**
 * Class CleanLineItems.
 */
trait CleanLineItems
{
    public function cleanItems($items): array
    {
        if (! isset($items) || ! is_array($items)) {
            return [];
        }

        $cleaned_items = [];

        foreach ($items as $item) {
            $cleaned_items[] = $this->cleanLineItem((array) $item);
        }

        return $cleaned_items;
    }

    public function cleanFeeItems($items): array
    {

        //ensure we never allow gateway fees to be cloned across to new entities
        foreach ($items as $key => $value) {
            if (in_array($value['type_id'], ['3','4'])) {
                unset($items[$key]);
            }
        }

        return $items;

    }

    /**
     * Sets default values for the line_items.
     * @param $item
     */
    private function cleanLineItem($item)
    {
        $invoice_item = (object) get_class_vars(InvoiceItem::class);

        unset($invoice_item->casts);

        foreach ($invoice_item as $key => $value) {
            $item[$key] = BaseSettings::castAttribute(
                InvoiceItem::$casts[$key],
                array_key_exists($key, $item) && isset($item[$key]) ? $item[$key] : $value
            );
        }

        if (empty($item['type_id']) || $item['type_id'] === '0') {
            $item['type_id'] = '1';
        }

        if (empty($item['tax_id'])) {
            $item['tax_id'] = ($item['type_id'] === '2') ? '2' : '1';
        }

        $xss_patterns = ["</sc", "onerror", "prompt(", "alert("];
        foreach (['notes', 'product_key', 'custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'] as $field) {
            if (!empty($item[$field]) && is_string($item[$field])) {
                $item[$field] = str_replace($xss_patterns, "<-", $item[$field]);
            }
        }

        unset($item['id'], $item['_id']);

        return $item;
    }

    private function entityTotalAmount($items)
    {
        $total = 0;

        foreach ($items as $item) {
            $total += ($item['cost'] * $item['quantity']);
        }

        return $total;
    }
}
