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

        // update invoice_item_type_id for task invoice items
        DB::statement('update invoice_items
            left join invoices on invoices.id = invoice_items.invoice_id
            set invoice_item_type_id = 2
            where invoices.has_tasks = 1');
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
