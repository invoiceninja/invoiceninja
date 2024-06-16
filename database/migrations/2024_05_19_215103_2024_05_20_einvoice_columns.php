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
        
        Schema::table('companies', function (Blueprint $table) {
            $table->mediumText('e_invoice')->nullable();
        });
        
        Schema::table('invoices', function (Blueprint $table) {
            $table->mediumText('e_invoice')->nullable();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->mediumText('e_invoice')->nullable();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->mediumText('e_invoice')->nullable();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->mediumText('e_invoice')->nullable();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->mediumText('e_invoice')->nullable();
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->integer('email_quota')->nullable();
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
