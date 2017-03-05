<?php

use Illuminate\Database\Migrations\Migration;

class SupportTokenBilling extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->smallInteger('token_billing_type_id')->default(TOKEN_BILLING_ALWAYS);
        });

        Schema::create('account_gateway_tokens', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('account_gateway_id');
            $table->unsignedInteger('client_id');
            $table->string('token');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('account_gateway_id')->references('id')->on('account_gateways')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });

        DB::table('accounts')->update(['token_billing_type_id' => TOKEN_BILLING_ALWAYS]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('token_billing_type_id');
        });

        Schema::drop('account_gateway_tokens');
    }
}
