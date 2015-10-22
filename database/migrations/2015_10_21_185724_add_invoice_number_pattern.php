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

        Schema::table('clients', function ($table) {
            $table->integer('invoice_number_counter')->default(1)->nullable();
            $table->integer('quote_number_counter')->default(1)->nullable();
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

        Schema::table('accounts', function ($table) {
            $table->dropColumn('invoice_number_counter');
            $table->dropColumn('quote_number_counter');
        });
    }
}
