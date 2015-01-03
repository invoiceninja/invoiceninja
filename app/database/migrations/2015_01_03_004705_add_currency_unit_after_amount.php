<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCurrencyUnitAfterAmount extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('currencies', function(Blueprint $table)
		{
			$table->boolean('unit_after_amount')->default(false);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('currencies', function(Blueprint $table)
		{
			$table->dropColumn('unit_after_amount');
		});
	}

}
