<?php

use Illuminate\Database\Migrations\Migration;

class AddDefaultTaxRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->unsignedInteger('default_tax_rate_id')->nullable();
            $table->smallInteger('recurring_hour')->default(DEFAULT_SEND_RECURRING_HOUR);
        });

        Schema::table('products', function ($table) {
            $table->unsignedInteger('default_tax_rate_id')->nullable();
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
            $table->dropColumn('default_tax_rate_id');
            $table->dropColumn('recurring_hour');
        });

        Schema::table('products', function ($table) {
            $table->dropColumn('default_tax_rate_id');
        });
    }
}
