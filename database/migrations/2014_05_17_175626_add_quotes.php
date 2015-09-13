<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQuotes extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('invoices', function($table)
		{
			$table->boolean('is_quote')->default(0);			
			$table->unsignedInteger('quote_id')->nullable();
			$table->unsignedInteger('quote_invoice_id')->nullable();			
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
			$table->dropColumn('is_quote');
			$table->dropColumn('quote_id');
			$table->dropColumn('quote_invoice_id');
		});
	}

}
