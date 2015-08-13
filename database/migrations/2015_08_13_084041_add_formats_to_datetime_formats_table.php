<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFormatsToDatetimeFormatsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('datetime_formats', function(Blueprint $t)
		{
            $t->string('format_sec');
            $t->string('format_moment');
            $t->string('format_moment_sec');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('datetime_formats', function(Blueprint $t)
		{
            $t->dropColumn('format_sec');
            $t->dropColumn('format_moment');
            $t->dropColumn('format_moment_sec');
		});
	}

}
