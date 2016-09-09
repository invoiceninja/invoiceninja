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
        Schema::dropIfExists('account_gateway_settings');
        Schema::dropIfExists('gateway_types');

        Schema::create('gateway_types', function($t)
        {
            $t->string('id');
            $t->string('name');

            $t->primary('id');
        });

        Schema::create('account_gateway_settings', function($t)
        {
            $t->increments('id');

            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->string('gateway_type_id')->nullable();

            $t->timestamp('updated_at')->nullable();


            $t->unsignedInteger('min_limit');
            $t->unsignedInteger('max_limit');

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('gateway_type_id')->references('id')->on('gateway_types')->onDelete('cascade');

        });

        Schema::table('payment_types', function($t)
        {
            $t->string('gateway_type_id')->nullable();
            $t->foreign('gateway_type_id')->references('id')->on('gateway_types')->onDelete('cascade');
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_gateway_settings');
        Schema::dropIfExists('gateway_types');
    }
}
