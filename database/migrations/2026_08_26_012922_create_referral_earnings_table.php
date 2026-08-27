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
        Schema::create('referral_earnings', function (Blueprint $table) {
            $table->id();
            $table->string('referral_code')->index();
            $table->string('account_key')->index();
            $table->unsignedInteger('client_id')->nullable();
            $table->unsignedTinyInteger('entity_type');
            $table->decimal('gross_amount', 20, 6)->default(0);
            $table->decimal('commission_amount', 20, 6)->default(0);
            $table->string('context')->nullable();
            $table->unsignedInteger('payment_id')->nullable()->index();
            $table->timestamps(6);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
