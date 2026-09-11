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
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->unsignedInteger('cash_discount_days')->nullable()->after('num_days');
            $table->decimal('cash_discount_percent', 20, 6)->nullable()->after('cash_discount_days');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('cash_discount_percent', 20, 6)->nullable()->after('location_id');
            $table->date('cash_discount_due_date')->nullable()->after('cash_discount_percent');
            $table->decimal('applied_cash_discount', 20, 6)->default(0)->after('paid_to_date');
        });

        Schema::table('paymentables', function (Blueprint $table) {
            $table->decimal('cash_discount', 20, 6)->default(0)->after('amount');
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
