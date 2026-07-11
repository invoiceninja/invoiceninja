<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->unsignedInteger('cash_discount_days')->nullable()->after('num_days');
            $table->decimal('cash_discount_percent', 20, 6)->nullable()->after('cash_discount_days');
        });
    }

    public function down(): void
    {
        Schema::table('payment_terms', function (Blueprint $table) {
            $table->dropColumn(['cash_discount_days', 'cash_discount_percent']);
        });
    }
};
