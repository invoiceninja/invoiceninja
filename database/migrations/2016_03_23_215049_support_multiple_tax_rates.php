<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class SupportMultipleTaxRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
	public function up()
	{
		Schema::table('invoices', function($table) {
			$table->decimal('tax_rate', 13, 3)->change();
		});

		Schema::table('invoice_items', function($table) {
			$table->decimal('tax_rate', 13, 3)->change();
		});
	}
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('invoices', function($table) {
			$table->decimal('tax_rate', 13, 2)->change();
		});

		Schema::table('invoice_items', function($table) {
			$table->decimal('tax_rate', 13, 2)->change();
		});
	}
}