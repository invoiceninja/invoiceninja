<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSwapCurrencySymbolToCurrency extends Migration
{
    const TABLE_NAME = 'currencies';
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(self::TABLE_NAME,function(Blueprint $table){
            $table->boolean('swap_currency_symbol')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(self::TABLE_NAME,function(Blueprint $table){
           $table->dropColumn('swap_currency_symbol');
        });
    }
}
