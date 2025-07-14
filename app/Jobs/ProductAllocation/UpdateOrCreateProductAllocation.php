<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\ProductAllocation;

use App\Libraries\MultiDB;
use App\Models\Product;
use App\Models\ProductAllocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateOrCreateProductAllocation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $line_items;

    public $invoice;

    public $company;

    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param $line_items
     * @param $invoice
     * @param $company
     */
    public function __construct($line_items, $invoice, $company)
    {
        $this->line_items = $line_items;

        $this->invoice = $invoice;

        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDB($this->company->db);

        // only update / create products + allocations - not tasks or gateway fees
        $updateable_products = collect($this->line_items)->filter(function ($item) {
            return $item->type_id == 1;
        });

        $used_product_allocation_ids = [];

        // CONNECT EXTERNAL ENTRIES => ensure marking productAllocations, when they are connected already
        // aggregates used product_allocation_ids
        $used_product_allocation_ids = collect($this->line_items)
            ->filter(function ($item) {
                return $item->type_id == 1;
            })
            ->pluck('product_allocation_ids')
            ->flatten()
            ->unique()
            ->map(function ($id) {
                return $this->invoice->decodePrimaryKey($id);
            })
            ->values();

        // assigns invoice to connected product_allocations
        ProductAllocation::whereIn('id', $used_product_allocation_ids)
            ->where('company_id', $this->invoice->company->id)
            ->whereNull('invoice_id')
            ->where(function ($query) {
                $query->whereNull('invoice_aggregation_key')
                    ->orWhere('invoice_aggregation_key', '!=', 'invoice-product-mapper');
            })
            ->update([
                'invoice_id' => $this->invoice->id,
            ]);
        // unconnect all invalid connected product_allocations
        ProductAllocation::whereNotIn('id', $used_product_allocation_ids)
            ->where('company_id', $this->invoice->company->id)
            ->where('invoice_id', $this->invoice->id)
            ->where(function ($query) {
                $query->whereNull('invoice_aggregation_key')
                    ->orWhere('invoice_aggregation_key', '!=', 'invoice-product-mapper');
            })
            ->update([
                'invoice_id' => null,
            ]);

        // CUSTOM MAPPER => create custom data in external table with data of products
        // remove all existing mappers
        ProductAllocation::where('company_id', $this->invoice->company->id)
            ->where('invoice_id', $this->invoice->id)
            ->where('invoice_aggregation_key', 'invoice-product-mapper')
            ->delete();

        $already_updated = [];
        /** @var \App\DataMapper\InvoiceItem $item */
        foreach ($updateable_products as $item) {

            if (empty($item->product_key) || (isset($item->product_allocation_ids) && count($item->product_allocation_ids) > 0) || in_array($item->product_key, $already_updated)) {
                continue;
            }

            // create virtual mapper, when no product_allocation_ids are used
            $product = Product::withTrashed()->firstOrNew(['product_key' => $item->product_key, 'company_id' => $this->invoice->company->id]);
            $already_updated[] = $item->product_key;

            $productAllocation = new ProductAllocation();
            $productAllocation->company_id = $this->invoice->company->id;
            $productAllocation->user_id = $this->invoice->user_id;
            $productAllocation->product_id = $product->id;
            $productAllocation->invoice_id = $this->invoice->id;
            $productAllocation->client_id = $this->invoice->client_id;
            $productAllocation->invoice_aggregation_key = 'invoice-product-mapper';
            $productAllocation->recurring_id = $this->invoice->recurring_id ?? null;
            $productAllocation->project_id = $this->invoice->project_id ?? null;
            $productAllocation->subscription_id = $this->invoice->subscription_id ?? null;

            // aggregate quantity of all items with same product_key & none linked product_allocation_ids
            $productAllocation->quantity = $updateable_products->filter(function ($i) use ($item) {
                return $i->product_key == $item->product_key && !(isset($item->product_allocation_ids) && count($item->product_allocation_ids) > 0);
            })->sum('quantity');

            // skip, when not required
            if ($productAllocation->quantity == 0) {
                continue;
            }

            // save
            $productAllocation->save();

        }
    }

    public function failed($exception = null)
    {
        info('update create failed with = ');
        nlog($exception->getMessage());
    }
}
