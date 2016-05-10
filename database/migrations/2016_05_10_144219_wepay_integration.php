<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class WePayIntegration extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_methods', function($table)
        {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('account_gateway_token_id');
            $table->unsignedInteger('payment_type_id');
            $table->string('source_reference');

            $table->unsignedInteger('routing_number')->nullable();
            $table->smallInteger('last4')->unsigned()->nullable();
            $table->date('expiration')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('status')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('account_gateway_token_id')->references('id')->on('account_gateway_tokens');
            $table->foreign('payment_type_id')->references('id')->on('payment_types');
            $table->foreign('currency_id')->references('id')->on('currencies');

            $table->unsignedInteger('public_id')->index();
            $table->unique( array('account_id','public_id') );
        });

        Schema::table('payments', function($table)
        {
            $table->unsignedInteger('payment_method_id')->nullable();
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });

        Schema::table('account_gateway_tokens', function($table)
        {
            $table->unsignedInteger('default_payment_method_id')->nullable();
            $table->foreign('default_payment_method_id')->references('id')->on('payment_methods');

            $table->boolean('uses_local_payment_methods')->defalut(true);
        });

        \DB::table('account_gateway_tokens')->update(array('uses_local_payment_methods' => false));
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payments', function($table)
        {
            $table->dropForeign('payments_payment_method_id_foreign');
            $table->dropColumn('payment_method_id');
        });

        Schema::table('account_gateway_tokens', function($table)
        {
            $table->dropForeign('account_gateway_tokens_default_payment_method_id_foreign');
            $table->dropColumn('default_payment_method_id');
            $table->dropColumn('uses_local_payment_methods');
        });

        Schema::dropIfExists('payment_methods');
    }
}
