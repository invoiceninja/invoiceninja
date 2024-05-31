<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::table('quotes', function (Blueprint $table) {
            $table->mediumText('tax_data')->nullable(); //json object
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->mediumText('tax_data')->nullable(); //json object
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->mediumText('tax_data')->nullable(); //json object
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
    }
};
