<?php

use Illuminate\Database\Migrations\Migration;

class AddPdfmakeSupport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_designs', function ($table) {
            $table->mediumText('pdfmake')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_designs', function ($table) {
            $table->dropColumn('pdfmake');
        });
    }
}
