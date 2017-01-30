<?php

use Illuminate\Database\Migrations\Migration;

class SupportLockingAccount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->smallInteger('failed_logins')->nullable();
        });

        Schema::table('account_gateways', function ($table) {
            $table->boolean('show_address')->default(true)->nullable();
            $table->boolean('update_address')->default(true)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('failed_logins');
        });

        Schema::table('account_gateways', function ($table) {
            $table->dropColumn('show_address');
            $table->dropColumn('update_address');
        });
    }
}
