<?php

use App\Utils\Ninja;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if(Ninja::isHosted()) {
            return;
        }
        
        set_time_limit(0);
        
        Invoice::withTrashed()
            ->where('is_deleted', false)
            ->cursor()
            ->each(function (Invoice $invoice) {    
                
                $hit = false;

                $line_items = $invoice->line_items;

                if(is_array($line_items))
                {
                    foreach ($line_items as $key => $item)
                    {

                        if($product = Product::where('company_id', $invoice->company_id)->where('product_key', $item->product_key)->where('cost', '>', 0)->first())
                        {
                            if((property_exists($item, 'product_cost') && $item->product_cost == 0) || !property_exists($item, 'product_cost')){
                                $hit = true;
                                $line_items[$key]->product_cost = (float)$product->cost;
                            }
                        }
                        
                    }
                    
                    if($hit){
                        $invoice->line_items = $line_items;
                        $invoice->saveQuietly();
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
