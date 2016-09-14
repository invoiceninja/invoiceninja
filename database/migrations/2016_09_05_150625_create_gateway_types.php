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
        Schema::create('gateway_types', function($t)
        {
            $t->increments('id');
            $t->string('alias');
            $t->string('name');
        });

        Schema::dropIfExists('account_gateway_settings');
        Schema::create('account_gateway_settings', function($t)
        {
            $t->increments('id');

            $t->unsignedInteger('account_id');
            $t->unsignedInteger('user_id');
            $t->unsignedInteger('gateway_type_id')->nullable();

            $t->timestamp('updated_at')->nullable();


            $t->unsignedInteger('min_limit')->nullable();
            $t->unsignedInteger('max_limit')->nullable();

            $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('gateway_type_id')->references('id')->on('gateway_types')->onDelete('cascade');

        });

        Schema::table('payment_types', function($t)
        {
            $t->unsignedInteger('gateway_type_id')->nullable();
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
        Schema::table('payment_types', function($t)
        {
            $t->dropForeign('payment_types_gateway_type_id_foreign');
            $t->dropColumn('gateway_type_id');
        });

        Schema::dropIfExists('account_gateway_settings');
        Schema::dropIfExists('gateway_types');
    }
}
