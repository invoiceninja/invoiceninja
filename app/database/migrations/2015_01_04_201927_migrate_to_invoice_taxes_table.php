<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MigrateToInvoiceTaxesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Migrate existing invoices
		$results = DB::table('invoices')->select('id', 'tax_rate', 'tax_name')->get();
		foreach ($results as $result)
    {
    	if (!empty($result->tax_name) || $result->tax_rate > 0.0) {
    		DB::table('invoice_taxes')->insert([
	      	"invoice_id" => $result->id,
	        "name" => $result->tax_name,
	        "rate" => $result->tax_rate
	      ]);
    	}
    }

    // Delete old column.
    Schema::table('invoices', function($table)
    {
    	$table->dropColumn('tax_name');
      $table->dropColumn('tax_rate');
    });
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		// There is no going back to 1-1 from a 1-n relationship
	}

}
