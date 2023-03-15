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

namespace App\Services\Recurring;

use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;

class UpdatePrices extends AbstractService
{
    public function __construct(public array $ids)
    {
    }

    public function run()
    {
        RecurringInvoice::whereIn('id', $this->ids)
                        ->cursor()
                        ->each(function ($recurring_invoice){

                        $line_items = $recurring_invoice->line_items;
                        foreach($line_items as $key => $line_item)
                        {

                            $product = Product::where('company_id', $recurring_invoice->company_id)
                            ->where('product_key', $line_item->product_key)
                            ->where('is_deleted', 0)
                            ->first();
                            
                            if($product){

                                $line_items[$key]->cost = $product->cost;
                            }

                        }

                        $recurring_invoice->line_items = $line_items;
                        $recurring_invoice->calc()->getInvoice()->save();
    
        });
    }
}