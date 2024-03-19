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

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount', 20, 6)->default(0)->change();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->decimal('discount', 20, 6)->default(0)->change();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('discount', 20, 6)->default(0)->change();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('discount', 20, 6)->default(0)->change();
        });
                
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->decimal('discount', 20, 6)->default(0)->change();
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
