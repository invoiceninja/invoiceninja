<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['transaction_reference']);
        });

        Schema::table('paymentables', function (Blueprint $table) {
            $table->index(['paymentable_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['recurring_id']);
            $table->index(['status_id','balance']);
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->index(['company_id','updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
