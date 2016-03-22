<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use DB;

class AddDocuments extends Migration {
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		/*Schema::table('accounts', function($table) {
			$table->string('logo')->nullable()->default(null);
			$table->unsignedInteger('logo_width');
			$table->unsignedInteger('logo_height');
			$table->unsignedInteger('logo_size');
		});*/
		
		DB::table('accounts')->update(array('logo' => ''));
	}
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('accounts', function($table) {
			$table->dropColumn('logo');
			$table->dropColumn('logo_width');
			$table->dropColumn('logo_height');
			$table->dropColumn('logo_size');
		});
	}
}
