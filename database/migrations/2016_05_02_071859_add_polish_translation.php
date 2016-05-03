<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPolishTranslation extends Migration
{
    const LANGUAGE_LOCALE = 'pl';
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('languages')->insert(['name' => 'Polish', 'locale' => self::LANGUAGE_LOCALE]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $language = \App\Models\Language::whereLocale(self::LANGUAGE_LOCALE)->first();
        $language->delete();
    }
}
