<?php

use Illuminate\Database\Migrations\Migration;

class AddPageSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->string('page_size')->default('A4');
            $table->boolean('live_preview')->default(true);
            $table->smallInteger('invoice_number_padding')->default(4);
        });

        Schema::table('fonts', function ($table) {
            $table->dropColumn('is_early_access');
        });

        Schema::create('expense_categories', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->string('name')->nullable();
            $table->unsignedInteger('public_id')->index();
        });

        Schema::table('expense_categories', function ($table) {
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('expenses', function ($table) {
            $table->unsignedInteger('expense_category_id')->nullable()->index();
        });

        Schema::table('expenses', function ($table) {
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('page_size');
            $table->dropColumn('live_preview');
            $table->dropColumn('invoice_number_padding');
        });

        Schema::table('fonts', function ($table) {
            $table->boolean('is_early_access');
        });

        Schema::table('expenses', function ($table) {
            $table->dropForeign('expenses_expense_category_id_foreign');
            $table->dropColumn('expense_category_id');
        });

        Schema::dropIfExists('expense_categories');
    }
}
