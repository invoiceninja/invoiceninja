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

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\InventoryNotificationObject;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class AdjustProductInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Company $company;

    public Invoice $invoice;

    public array $old_invoice;

    public function __construct(Company $company, Invoice $invoice, ?array $old_invoice = [])
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

        if (count($this->old_invoice) > 0) {
            $this->existingInventoryAdjustment();
        }

        return $this->newInventoryAdjustment();
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->company_key)];
    }

    private function newInventoryAdjustment()
    {
        $line_items = $this->invoice->line_items;

        foreach ($line_items as $item) {
            $p = Product::where('product_key', $item->product_key)->where('company_id', $this->company->id)->where('in_stock_quantity', '>', 0)->first();

            if (! $p) {
                continue;
            }

            $p->in_stock_quantity -= $item->quantity;
            $p->saveQuietly();

            if ($p->stock_notification_threshold && $p->in_stock_quantity <= $p->stock_notification_threshold) {
                $this->notifyStockLevels($p, 'product');
            } elseif ($this->company->stock_notification_threshold && $p->in_stock_quantity <= $this->company->stock_notification_threshold) {
                $this->notifyStocklevels($p, 'company');
            }
        }
    }

    private function existingInventoryAdjustment()
    {
        foreach ($this->old_invoice as $item) {
            $p = Product::where('product_key', $item->product_key)->where('company_id', $this->company->id)->first();

            if (! $p) {
                continue;
            }

            $p->in_stock_quantity += $item->quantity;
            $p->saveQuietly();
        }
    }

    private function notifyStocklevels(Product $product, string $notification_level)
    {
        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new InventoryNotificationObject($product, $notification_level))->build());
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        $nmo->to_user = $this->company->owner();

        NinjaMailerJob::dispatch($nmo);
    }
}
