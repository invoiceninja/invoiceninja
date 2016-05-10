<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddThaiTranslation extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		DB::table('languages')->insert(['name' => 'Thai', 'locale' => 'th']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $language = \App\Models\Language::whereLocale('th')->first();
		$language->delete();
    }
}
