<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSvLanguage extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        DB::table('languages')->insert(['name' => 'Swedish', 'locale' => 'sv']);
        DB::table('languages')->insert(['name' => 'Spanish - Spain', 'locale' => 'es_ES']);
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        if ($language = \App\Models\Language::whereLocale('sv')->first()) {
            $language->delete();
        }

        if ($language = \App\Models\Language::whereLocale('es_ES')->first()) {
            $language->delete();
        }
	}

}
