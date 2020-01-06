<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class IncreaseExchangeRatePrecision extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payments', function ($table) {
            $table->decimal('exchange_rate', 23, 14)->change();
        });
		Schema::table('expenses', function ($table) {
            $table->decimal('exchange_rate', 23, 14)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payments', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->change();
        });
		Schema::table('expenses', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->change();
        });
    }
}
