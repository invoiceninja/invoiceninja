<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('currency_id')->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedInteger('subscription_id')->nullable();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->unsignedInteger('subscription_id')->nullable();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->unsignedInteger('subscription_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
