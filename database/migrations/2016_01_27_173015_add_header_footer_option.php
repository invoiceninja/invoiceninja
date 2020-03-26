<?php

use Illuminate\Database\Migrations\Migration;

class AddHeaderFooterOption extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->boolean('all_pages_footer');
            $table->boolean('all_pages_header');
            $table->boolean('show_currency_code');
            $table->date('pro_plan_trial')->nullable();
        });

        Schema::table('gateways', function ($table) {
            $table->boolean('is_offsite');
            $table->boolean('is_secure');
        });

        Schema::table('expenses', function ($table) {
            if (Schema::hasColumn('expenses', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->change();
                $table->unsignedInteger('bank_id')->nullable()->change();
            }
        });

        Schema::table('vendors', function ($table) {
            if (Schema::hasColumn('vendors', 'transaction_name')) {
                $table->string('transaction_name')->nullable()->change();
            }
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
            $table->dropColumn('all_pages_footer');
            $table->dropColumn('all_pages_header');
            $table->dropColumn('show_currency_code');
            $table->dropColumn('pro_plan_trial');
        });

        Schema::table('gateways', function ($table) {
            $table->dropColumn('is_offsite');
            $table->dropColumn('is_secure');
        });
    }
}
