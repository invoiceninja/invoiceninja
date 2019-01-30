<?php

use Illuminate\Database\Migrations\Migration;

class AddBankSubaccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bank_subaccounts', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('bank_account_id');

            $table->string('account_name');
            $table->string('account_number');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('expenses', function ($table) {
            $table->string('transaction_id')->nullable();
            $table->unsignedInteger('bank_id')->nullable();
        });

        Schema::table('vendors', function ($table) {
            $table->string('transaction_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bank_subaccounts');

        Schema::table('expenses', function ($table) {
            $table->dropColumn('transaction_id');
            $table->dropColumn('bank_id');
        });

        Schema::table('vendors', function ($table) {
            $table->dropColumn('transaction_name');
        });
    }
}
