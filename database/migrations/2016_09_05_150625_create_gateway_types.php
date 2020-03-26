<?php

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
        Schema::create('gateway_types', function ($table) {
            $table->increments('id');
            $table->string('alias');
            $table->string('name');
        });

        Schema::dropIfExists('account_gateway_settings');
        Schema::create('account_gateway_settings', function ($table) {
            $table->increments('id');

            $table->unsignedInteger('account_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('gateway_type_id')->nullable();

            $table->timestamp('updated_at')->nullable();

            $table->unsignedInteger('min_limit')->nullable();
            $table->unsignedInteger('max_limit')->nullable();
        });

        Schema::table('account_gateway_settings', function ($table) {
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('gateway_type_id')->references('id')->on('gateway_types')->onDelete('cascade');
        });

        Schema::table('payment_types', function ($table) {
            $table->unsignedInteger('gateway_type_id')->nullable();
        });

        // http://laravel.io/forum/09-18-2014-foreign-key-not-saving-in-migration
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::table('payment_types', function ($table) {
            $table->foreign('gateway_type_id')->references('id')->on('gateway_types')->onDelete('cascade');
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('payment_types', function ($table) {
            $table->dropForeign('payment_types_gateway_type_id_foreign');
            $table->dropColumn('gateway_type_id');
        });

        Schema::dropIfExists('account_gateway_settings');
        Schema::dropIfExists('gateway_types');
    }
}
