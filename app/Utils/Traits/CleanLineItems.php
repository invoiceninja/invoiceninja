<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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

    /**
     * Sets default values for the line_items.
     * @param $item
     */
    private function cleanLineItem($item)
    {
        $invoice_item = (object) get_class_vars(InvoiceItem::class);

        unset($invoice_item->casts);

        foreach ($invoice_item as $key => $value) {
            //if the key has not been set, we set it to a default value
            if (! array_key_exists($key, $item) || ! isset($item[$key])) {
                $item[$key] = $value;
                $item[$key] = BaseSettings::castAttribute(InvoiceItem::$casts[$key], $value);
            } else {
                //always cast the value!
                $item[$key] = BaseSettings::castAttribute(InvoiceItem::$casts[$key], $item[$key]);
            }

            if (array_key_exists('type_id', $item) && $item['type_id'] == '0') {
                $item['type_id'] = '1';
            }

            if (! array_key_exists('type_id', $item)) {
                $item['type_id'] = '1';
            }

            if (! array_key_exists('tax_id', $item)) {
                $item['tax_id'] = '1';
            } elseif(array_key_exists('tax_id', $item) && $item['tax_id'] == '') {

                if($item['type_id'] == '2') {
                    $item['tax_id'] = '2';
                } else {
                    $item['tax_id'] = '1';
                }

            }

            if(isset($item['notes'])) {
                $item['notes'] = str_replace("</sc", "<-", $item['notes']);
            }

            if(isset($item['product_key'])) {
                $item['product_key'] = str_replace("</sc", "<-", $item['product_key']);
            }

        }

        if (array_key_exists('id', $item) || array_key_exists('_id', $item)) {
            unset($item['id']);
        }

        return $item;
    }
}
