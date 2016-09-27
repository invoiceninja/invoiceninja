<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGatewayTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('gateway_types');
        Schema::create('gateway_types', function($table)
        {
            $table->increments('id');
            $table->string('alias');
            $table->string('name');
        });

        Schema::dropIfExists('account_gateway_settings');
        Schema::create('account_gateway_settings', function($table)
        {
            $table->increments('id');

            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('gateway_type_id')->nullable();

            $table->timestamp('updated_at')->nullable();


            $table->unsignedInteger('min_limit')->nullable();
            $table->unsignedInteger('max_limit')->nullable();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('gateway_type_id')->references('id')->on('gateway_types')->onDelete('cascade');

        });

        Schema::table('payment_types', function($table)
        {
            $table->unsignedInteger('gateway_type_id')->nullable();
        });

        Schema::table('payment_types', function($table)
        {
            $table->foreign('gateway_type_id')->references('id')->on('gateway_types')->onDelete('cascade');
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_types', function($table)
        {
            $table->dropForeign('payment_types_gateway_type_id_foreign');
            $table->dropColumn('gateway_type_id');
        });

        Schema::dropIfExists('account_gateway_settings');
        Schema::dropIfExists('gateway_types');
    }
}
