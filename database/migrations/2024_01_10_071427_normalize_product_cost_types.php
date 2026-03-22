<?php

use App\Utils\Ninja;
use App\Models\Invoice;
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

                $line_items = $invoice->line_items;

                if(is_array($line_items))
                {
                    foreach ($line_items as $key => $item) {

                        if(property_exists($item, 'product_cost')) {
                            $line_items[$key]->product_cost = (float) $line_items[$key]->product_cost;
                        }

                    }
                }
                
                $invoice->line_items = $line_items;
                $invoice->saveQuietly();

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
