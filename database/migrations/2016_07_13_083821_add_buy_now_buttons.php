<?php

use Illuminate\Database\Migrations\Migration;

class AddBuyNowButtons extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->boolean('enable_buy_now_buttons')->default(false);
            $table->dropColumn('invoice_design');
        });

        Schema::table('datetime_formats', function ($table) {
            $table->dropColumn('label');
        });

        Schema::table('date_formats', function ($table) {
            $table->dropColumn('label');
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
            $table->dropColumn('enable_buy_now_buttons');
            $table->text('invoice_design')->nullable();
        });

        Schema::table('datetime_formats', function ($table) {
            $table->string('label');
        });

        Schema::table('date_formats', function ($table) {
            $table->string('label');
        });
    }
}
