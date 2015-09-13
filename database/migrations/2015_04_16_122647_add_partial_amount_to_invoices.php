<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPartialAmountToInvoices extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::table('invoices', function($table)
        {
            $table->decimal('partial', 13, 2)->nullable();
        });

        Schema::table('accounts', function($table)
        {
            $table->boolean('utf8_invoices')->default(true);
            $table->boolean('auto_wrap')->default(false);
            $table->string('subdomain')->nullable();
        });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::table('invoices', function($table)
        {
            $table->dropColumn('partial');
        });

        Schema::table('accounts', function($table)
        {
            $table->dropColumn('utf8_invoices');
            $table->dropColumn('auto_wrap');
            $table->dropColumn('subdomain');
        });
	}

}
