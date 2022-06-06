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

namespace App\Jobs\Inventory;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

//todo - ensure we are MultiDB Aware in dispatched jobs

class AdjustProductInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Company $company;

    public Invoice $invoice;

    public array $old_invoice;

    public function __construct(Company $company, Invoice $invoice, array $old_invoice = [])
    {

        $this->company = $company;
        $this->invoice = $invoice;
        $this->old_invoice = $old_invoice;
    }

    /**
     * Execute the job.
     *
     *
     * @return false
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        if(count($this->old_invoice) > 0)
            return $this->existingInventoryAdjustment();

        return $this->newInventoryAdjustment();

    }

    private function newInventoryAdjustment()
    {
        
        $line_items = $this->invoice->line_items;

        foreach($line_items as $item)
        {

            $p = Product::where('product_key', $item->product_key)->where('company_id', $this->company->id)->where('in_stock_quantity', '>', 0)->first();
            $p->in_stock_quantity -= $item->quantity;
            $p->save();

            //check threshols and notify user

            if($p->stock_notification_threshold && $p->in_stock_quantity <= $p->stock_notification_threshold)
                $this->notifyStockLevels($p, 'product');
            elseif($this->company->stock_notification_threshold && $p->in_stock_quantity <= $this->company->stock_notification_threshold){
                $this->notifyStocklevels($p, 'company');
            }
        }

    }

    private function existingInventoryAdjustment()
    {

    }

    private function notifyStocklevels(Product $product, string $notification_level)
    {

    }

}
