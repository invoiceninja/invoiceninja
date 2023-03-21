<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->mediumText('tax_data')->nullable(); //json object
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('calculate_taxes')->default(false); //setting to turn on/off tax calculations
            $table->boolean('tax_all_products')->default(false); //globally tax all products if none defined
        });

        Schema::table('products', function (Blueprint $table){
            $table->unsignedInteger('tax_id')->nullable(); // the product tax constant
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
};
