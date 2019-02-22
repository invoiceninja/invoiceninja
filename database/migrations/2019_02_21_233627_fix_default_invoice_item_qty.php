<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class FixDefaultInvoiceItemQty extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_items', function ($table) {
            $table->decimal('qty', 15, 4)->default(1)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_items', function ($table) {
            $table->decimal('qty', 15, 4)->default(0)->change();
        });
    }
}
