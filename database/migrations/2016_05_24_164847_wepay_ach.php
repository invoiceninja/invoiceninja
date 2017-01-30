<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class WepayAch extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('contact_key')->nullable()->default(null)->index()->unique();
        });

        Schema::table('payment_methods', function ($table) {
            $table->string('bank_name')->nullable();
            $table->string('ip')->nullable();
        });

        Schema::table('payments', function ($table) {
            $table->string('bank_name')->nullable();
            $table->string('ip')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('auto_bill_on_due_date')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('contact_key');
        });

        Schema::table('payments', function ($table) {
            $table->dropColumn('bank_name');
            $table->dropColumn('ip');
        });

        Schema::table('payment_methods', function ($table) {
            $table->dropColumn('bank_name');
            $table->dropColumn('ip');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('auto_bill_on_due_date');
        });
    }
}
