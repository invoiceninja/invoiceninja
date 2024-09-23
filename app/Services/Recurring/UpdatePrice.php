<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Recurring;

use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;

class UpdatePrice extends AbstractService
{
    public function __construct(public RecurringInvoice $recurring_invoice)
    {
    }

    public function run()
    {
        $line_items = $this->recurring_invoice->line_items;

        foreach ($line_items as $key => $line_item) {

            /** @var \App\Models\Product $product **/
            $product = Product::query()->where('company_id', $this->recurring_invoice->company_id)
            ->where('product_key', $line_item->product_key)
            ->where('is_deleted', 0)
            ->first();

            if ($product) { //@phpstan-ignore-line
                $line_items[$key]->cost = floatval($product->price);
            }
        }

        $this->recurring_invoice->line_items = $line_items;

        $this->recurring_invoice->calc()->getInvoice()->save();
    }
}
