<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paymentables', function (Blueprint $table) {
            $table->decimal('cash_discount', 20, 6)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('paymentables', function (Blueprint $table) {
            $table->dropColumn('cash_discount');
        });
    }
};
