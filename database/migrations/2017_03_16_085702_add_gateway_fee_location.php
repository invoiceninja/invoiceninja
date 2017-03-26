<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddGatewayFeeLocation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('auto_wrap');
            $table->dropColumn('utf8_invoices');
            $table->boolean('gateway_fee_enabled')->default(0);
            $table->date('reset_counter_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('gateway_fee_enabled');
            $table->dropColumn('reset_counter_date');
        });
    }
}
