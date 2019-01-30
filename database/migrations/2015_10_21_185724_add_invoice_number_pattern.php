<?php

use Illuminate\Database\Migrations\Migration;

class AddInvoiceNumberPattern extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->string('invoice_number_pattern')->nullable();
            $table->string('quote_number_pattern')->nullable();
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
            $table->dropColumn('invoice_number_pattern');
            $table->dropColumn('quote_number_pattern');
        });
    }
}
