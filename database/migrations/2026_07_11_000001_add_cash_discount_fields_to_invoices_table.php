<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('cash_discount_percent', 20, 6)->nullable()->after('location_id');
            $table->date('cash_discount_expiry_date')->nullable()->after('cash_discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['cash_discount_percent', 'cash_discount_expiry_date']);
        });
    }
};
