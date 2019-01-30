<?php

use Illuminate\Database\Migrations\Migration;

class ImproveCurrencyLocalization extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function ($table) {
            $table->boolean('swap_currency_symbol')->default(0);
            $table->string('thousand_separator')->nullable();
            $table->string('decimal_separator')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('countries', function ($table) {
            $table->dropColumn('swap_currency_symbol');
            $table->dropColumn('thousand_separator');
            $table->dropColumn('decimal_separator');
        });
    }
}
