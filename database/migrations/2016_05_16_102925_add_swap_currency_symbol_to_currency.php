<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddSwapCurrencySymbolToCurrency extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->boolean('swap_currency_symbol')->default(false);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('tax_name1')->nullable();
            $table->decimal('tax_rate1', 13, 3);
            $table->string('tax_name2')->nullable();
            $table->decimal('tax_rate2', 13, 3);
        });

        Schema::table('account_gateways', function (Blueprint $table) {
            $table->boolean('require_cvv')->default(true)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('swap_currency_symbol');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('tax_name1');
            $table->dropColumn('tax_rate1');
            $table->dropColumn('tax_name2');
            $table->dropColumn('tax_rate2');
        });

        Schema::table('account_gateways', function (Blueprint $table) {
            $table->dropColumn('require_cvv');
        });
    }
}
