<?php

use Illuminate\Database\Migrations\Migration;

class AddFontSize extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->smallInteger('font_size')->default(DEFAULT_FONT_SIZE);
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
            $table->dropColumn('font_size');
        });
    }
}
