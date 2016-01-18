<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddQuoteToInvoiceOption extends Migration {

	/**
	 * Run the migrations.
     * Make the conversion of a quote into an invoice automatically after a client approves optional.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('accounts', function(Blueprint $table)
		{
			$table->smallInteger('quote_to_invoice')->default(1);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('accounts', function(Blueprint $table)
		{
            $table->dropColumn('quote_to_invoice');
		});
	}

}
