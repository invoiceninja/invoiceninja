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
        'filename'=>'js/templates/clean.js'
        ]);
    DB::table('invoice_designs')->where('id', 2)->update([
        'filename'=>'js/templates/bold.js'
        ]);
    DB::table('invoice_designs')->where('id', 3)->update([
        'filename'=>'js/templates/modern.js'
        ]);
    DB::table('invoice_designs')->where('id', 4)->update([
        'filename'=>'js/templates/plain.js'
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
