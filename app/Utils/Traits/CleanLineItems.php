<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

use App\DataMapper\BaseSettings;
use App\DataMapper\InvoiceItem;

/**
 * Class CleanLineItems.
 */
trait CleanLineItems
{
    public function cleanItems($items) :array
    {
        if (! isset($items)) {
            return [];
        }

        $cleaned_items = [];

        foreach ($items as $item) {
            $cleaned_items[] = $this->cleanLineItem($item);
        }

        return $cleaned_items;
    }

    /**
     * Sets default values for the line_items.
     * @return $this
     */
    private function cleanLineItem($item)
    {
        $invoice_item = (object) get_class_vars(InvoiceItem::class);
        unset($invoice_item->casts);

        foreach ($invoice_item as $key => $value) {
            if (! array_key_exists($key, $item) || ! isset($item[$key])) {
                $item[$key] = $value;
                $item[$key] = BaseSettings::castAttribute(InvoiceItem::$casts[$key], $value);
            }
        }

        if (array_key_exists('id', $item)) {
            unset($item['id']);
        }

        return $item;
    }
}
