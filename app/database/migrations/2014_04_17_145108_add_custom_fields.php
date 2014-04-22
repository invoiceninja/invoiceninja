<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomFields extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('accounts', function($table)
		{
			$table->string('custom_label1');
			$table->string('custom_value1');

			$table->string('custom_label2');
			$table->string('custom_value2');

			$table->string('custom_client_label1');			
			$table->string('custom_client_label2');
		});	

		Schema::table('clients', function($table)
		{
			$table->string('custom_value1');
			$table->string('custom_value2');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('accounts', function($table)
		{
			$table->dropColumn('custom_label1');
			$table->dropColumn('custom_value1');

			$table->dropColumn('custom_label2');
			$table->dropColumn('custom_value2');

			$table->dropColumn('custom_client_label1');			
			$table->dropColumn('custom_client_label2');
		});	

		Schema::table('clients', function($table)
		{
			$table->dropColumn('custom_value1');
			$table->dropColumn('custom_value2');
		});
	}

}
