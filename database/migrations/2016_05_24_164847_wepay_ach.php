<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class WePayAch extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contacts', function(Blueprint $table) {
            $table->string('contact_key')->index()->default(null);
        });

        Schema::table('payment_methods', function($table)
        {
            $table->string('bank_name')->nullable();
        });

        Schema::table('payments', function($table)
        {
            $table->string('bank_name')->nullable();
        });

        Schema::table('accounts', function($table)
        {
            $table->boolean('auto_bill_on_due_date')->default(false);
        });

        DB::statement('ALTER TABLE `payments` ADD `ip_address` VARBINARY(16)');
        DB::statement('ALTER TABLE `payment_methods` ADD `ip_address` VARBINARY(16)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contacts', function(Blueprint $table) {
            $table->dropColumn('contact_key');
        });

        Schema::table('payments', function($table) {
            $table->dropColumn('ip_address');
        });

        Schema::table('payment_methods', function($table) {
            $table->dropColumn('ip_address');
        });
    }
}
