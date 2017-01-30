<?php

use Illuminate\Database\Migrations\Migration;

class AddTokens extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_tokens', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->string('name')->nullable();
            $table->string('token')->unique();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->unsignedInteger('public_id')->nullable();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('activities', function ($table) {
            $table->unsignedInteger('token_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('account_tokens');

        Schema::table('activities', function ($table) {
            $table->dropColumn('token_id');
        });
    }
}
