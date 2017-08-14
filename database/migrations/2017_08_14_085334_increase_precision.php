<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IncreasePrecision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function ($table) {
            $table->decimal('cost', 15, 4)->change();
            $table->decimal('qty', 15, 4)->change();
        });

        Schema::table('invoice_items', function ($table) {
            $table->decimal('cost', 15, 4)->change();
            $table->decimal('qty', 15, 4)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
