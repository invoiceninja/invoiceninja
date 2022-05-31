<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InventoryManagementSchema extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {


        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('enable_applying_payments')->default(0);
            $table->boolean('track_inventory')->default(0);
            $table->integer('inventory_notification_threshold')->nullable();

        });

        Schema::table('products', function (Blueprint $table){
            $table->integer('in_stock_quantity')->nullable();
            $table->integer('stock_notification_threshold')->nullable();
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

