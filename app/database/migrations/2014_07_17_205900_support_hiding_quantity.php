<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SupportHidingQuantity extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('accounts', function($table)
		{
			$table->boolean('hide_quantity');
			$table->boolean('hide_paid_to_date');

			$table->string('custom_invoice_label1');
			$table->string('custom_invoice_label2');			

			$table->boolean('custom_invoice_taxes1');
			$table->boolean('custom_invoice_taxes2');
		});

		Schema::table('invoices', function($table)
		{
			$table->decimal('custom_value1', 13, 2);
			$table->decimal('custom_value2', 13, 2);

			$table->boolean('custom_taxes1');
			$table->boolean('custom_taxes2');			
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
			$table->dropColumn('hide_quantity');
			$table->dropColumn('hide_paid_to_date');

			$table->dropColumn('custom_invoice_label1');
			$table->dropColumn('custom_invoice_label2');						

			$table->dropColumn('custom_invoice_taxes1');
			$table->dropColumn('custom_invoice_taxes2');			
		});	
		
		Schema::table('invoices', function($table)
		{
			$table->dropColumn('custom_value1');
			$table->dropColumn('custom_value2');

			$table->dropColumn('custom_taxes1');
			$table->dropColumn('custom_taxes2');						
		});		
	}

}
