<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomProductFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
        });

        Schema::table('account_gateway_settings', function ($table) {
            $table->decimal('fee_amount', 13, 2)->nullable();
            $table->decimal('fee_percent', 13, 3)->nullable();
            $table->string('fee_tax_name1')->nullable();
            $table->string('fee_tax_name2')->nullable();
            $table->decimal('fee_tax_rate1', 13, 3)->nullable();
            $table->decimal('fee_tax_rate2', 13, 3)->nullable();
        });

        Schema::table('invoice_items', function ($table) {
            $table->smallInteger('invoice_item_type_id')->default(1);
        });

        Schema::table('accounts', function ($table) {
            $table->smallInteger('reset_counter_frequency_id')->nullable();
            $table->smallInteger('payment_type_id')->nullable();
        });

        DB::table('currencies')->where('code', '=', 'HKR')->update(['code' => 'HRK']);

        // Add 'Two Months' frequency option
        if (DB::table('frequencies')->count() == 7) {
            DB::table('frequencies')->where('id', '=', 5)->update(['name' => 'Two months']);
            DB::table('frequencies')->where('id', '=', 6)->update(['name' => 'Three months']);
            DB::table('frequencies')->where('id', '=', 7)->update(['name' => 'Six months']);
            DB::table('frequencies')->insert(['name' => 'Yearly']);
            DB::statement('update invoices set frequency_id = frequency_id + 1 where frequency_id >= 5');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function ($table) {
            $table->dropColumn('custom_value1');
            $table->dropColumn('custom_value2');
        });

        Schema::table('account_gateway_settings', function ($table) {
            $table->dropColumn('fee_amount');
            $table->dropColumn('fee_percent');
            $table->dropColumn('fee_tax_rate1');
            $table->dropColumn('fee_tax_name1');
            $table->dropColumn('fee_tax_rate2');
            $table->dropColumn('fee_tax_name2');
        });

        Schema::table('invoice_items', function ($table) {
            $table->dropColumn('invoice_item_type_id');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('reset_counter_frequency_id');
            $table->dropColumn('payment_type_id');
        });

        DB::table('currencies')->where('code', '=', 'HRK')->update(['code' => 'HKR']);
    }
}
