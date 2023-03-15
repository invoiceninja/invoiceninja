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

class IncreasePrices extends AbstractService
{

    public function __construct(public array $ids, public float $percentage)
    {

    }

    public function run()
    {


        RecurringInvoice::whereIn('id', $this->ids)
                        ->cursor()
                        ->each(function ($recurring_invoice) {
                            
                            $line_items = $recurring_invoice->line_items;
                            foreach ($line_items as $key => $line_item) {

                                $line_items[$key]->cost = $line_item->cost * (1 + round(($this->percentage / 100), 2));
                                
                            }

                            $recurring_invoice->line_items = $line_items;
                            $recurring_invoice->calc()->getInvoice()->save();
                        });


    }


}
