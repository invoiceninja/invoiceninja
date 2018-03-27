<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBarcodeManufacturerNumberProductFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function ($table) {
            $table->string('barcode')->nullable();
            $table->string('manufacturer_part_number')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('products_barcode')->default(false);
            $table->boolean('products_manufacturer_part_number')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function ($table) {
            $table->dropColumn('barcode');
            $table->dropColumn('manufacturer_part_number');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('products_barcode');
            $table->dropColumn('products_manufacturer_part_number');
        });
    }
}
