<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InvoicesFile extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
    Schema::table('invoice_designs', function($table)
		{
      $table->text('filename')->nullable();
    });
    
    DB::table('invoice_designs')->where('id', 1)->update([
        'javascript' => '',
        'filename'=>'js/templates/clean.js'
        ]);
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('invoice_designs', function($table)
		{
			$table->dropColumn('filename');
		});
	}

}
