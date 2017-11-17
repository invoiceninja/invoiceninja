<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubdomainToLookups extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lookup_accounts', function ($table) {
            $table->string('subdomain')->nullable()->unique();
        });

        Schema::table('payments', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->default(1);
            $table->unsignedInteger('exchange_currency_id')->nullable(false);
        });

        Schema::table('expenses', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->default(1)->change();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lookup_accounts', function ($table) {
            $table->dropColumn('subdomain');
        });

        Schema::table('payments', function ($table) {
            $table->dropColumn('exchange_rate');
            $table->dropColumn('exchange_currency_id');
        });
    }
}
