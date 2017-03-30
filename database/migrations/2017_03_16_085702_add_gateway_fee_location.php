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
            if (Schema::hasColumn('accounts', 'auto_wrap')) {
                $table->dropColumn('auto_wrap');
            }
            if (Schema::hasColumn('accounts', 'utf8_invoices')) {
                $table->dropColumn('utf8_invoices');
            }
            if (Schema::hasColumn('accounts', 'dark_mode')) {
                $table->dropColumn('dark_mode');
            }
            $table->boolean('gateway_fee_enabled')->default(0);
            $table->date('reset_counter_date')->nullable();
        });

        Schema::table('clients', function ($table) {
            $table->integer('invoice_number_counter')->default(1)->nullable();
            $table->integer('quote_number_counter')->default(1)->nullable();
        });

        Schema::table('credits', function ($table) {
            $table->text('public_notes')->nullable();
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

        Schema::table('clients', function ($table) {
            $table->dropColumn('invoice_number_counter');
            $table->dropColumn('quote_number_counter');
        });

        Schema::table('credits', function ($table) {
            $table->dropColumn('public_notes');
        });
    }
}
