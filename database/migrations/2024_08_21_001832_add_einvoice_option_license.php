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
        
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('e_invoice_quota')->nullable()->index();
        });

        Schema::table('bank_transaction_rules', function (Blueprint $table){
            $table->enum('on_credit_match', ['create_payment', 'link_payment'])->default('create_payment');
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
