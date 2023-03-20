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

namespace App\Jobs\Inventory;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\InventoryNotificationObject;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class AdjustProductInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UserNotifies;

    public function __construct(public Company $company, public Invoice $invoice, public $old_invoice = [])
    {
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

    public function handleDeletedInvoice()
    {
        MultiDB::setDb($this->company->db);

        // foreach ($this->invoice->line_items as $item) {
        //     $p = Product::where('product_key', $item->product_key)->where('company_id', $this->company->id)->first();

        //     if (! $p) {
        //         continue;
        //     }

        //     $p->in_stock_quantity += $item->quantity;

        //     $p->saveQuietly();
        // }

        collect($this->invoice->line_items)->filter(function ($item) {
            return $item->type_id == '1';
        })->each(function ($i) {
            $p = Product::where('product_key', $i->product_key)->where('company_id', $this->company->id)->first();

            if ($p) {
                $p->in_stock_quantity += $i->quantity;

                $p->saveQuietly();
            }
        });
    }

    public function handleRestoredInvoice()
    {
        MultiDB::setDb($this->company->db);

        collect($this->invoice->line_items)->filter(function ($item) {
            return $item->type_id == '1';
        })->each(function ($i) {
            $p = Product::where('product_key', $i->product_key)->where('company_id', $this->company->id)->first();

            if ($p) {
                $p->in_stock_quantity -= $i->quantity;

                $p->saveQuietly();
            }
        });
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->company_key)];
    }

    private function newInventoryAdjustment()
    {

        collect($this->invoice->line_items)->filter(function ($item) {
            return $item->type_id == '1';
        })->each(function ($i) {
            $p = Product::where('product_key', $i->product_key)->where('company_id', $this->company->id)->first();

            if ($p) {
                $p->in_stock_quantity -= $i->quantity;

                $p->saveQuietly();
                nlog($p->stock_notification_threshold);
                nlog($p->in_stock_quantity);
                nlog($p->stock_notification_threshold);

                if ($this->company->stock_notification && $p->stock_notification && $p->stock_notification_threshold && $p->in_stock_quantity <= $p->stock_notification_threshold) {
                    $this->notifyStockLevels($p, 'product');
                } elseif ($this->company->stock_notification && $p->stock_notification && $this->company->inventory_notification_threshold && $p->in_stock_quantity <= $this->company->inventory_notification_threshold) {
                    $this->notifyStocklevels($p, 'company');
                }
            }
        });
    }

    private function existingInventoryAdjustment()
    {
    
        collect($this->invoice->line_items)->filter(function ($item) {
            return $item->type_id == '1';
        })->each(function ($i) {
            $p = Product::where('product_key', $i->product_key)->where('company_id', $this->company->id)->first();

            if ($p) {
                $p->in_stock_quantity += $i->quantity;

                $p->saveQuietly();
            }
        });
    }

    private function notifyStocklevels(Product $product, string $notification_level)
    {
        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new InventoryNotificationObject($product, $notification_level))->build());
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;

        $this->company->company_users->each(function ($cu) use ($product, $nmo) {
            if ($this->checkNotificationExists($cu, $product, ['inventory_all', 'inventory_user', 'inventory_threshold_all', 'inventory_threshold_user'])) {
                $nmo->to_user = $cu->user;
                NinjaMailerJob::dispatch($nmo);
            }
        });
    }
}
