<?php

use Illuminate\Database\Migrations\Migration;

class AddLanguageSupport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('languages', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('locale');
        });

    //DB::table('languages')->insert(['name' => 'English', 'locale' => 'en']);
    //DB::table('languages')->insert(['name' => 'Italian', 'locale' => 'it']);
    //DB::table('languages')->insert(['name' => 'German', 'locale' => 'de']);
    //DB::table('languages')->insert(['name' => 'French', 'locale' => 'fr']);
    //DB::table('languages')->insert(['name' => 'Brazilian Portuguese', 'locale' => 'pt_BR']);
    //DB::table('languages')->insert(['name' => 'Dutch', 'locale' => 'nl']);
    //DB::table('languages')->insert(['name' => 'Spanish', 'locale' => 'es']);
    //DB::table('languages')->insert(['name' => 'Norwegian', 'locale' => 'nb_NO']);

        Schema::table('accounts', function ($table) {
            $table->unsignedInteger('language_id')->default(1);
        });

        DB::table('accounts')->update(['language_id' => 1]);

        Schema::table('accounts', function ($table) {
            $table->foreign('language_id')->references('id')->on('languages');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropForeign('accounts_language_id_foreign');
            $table->dropColumn('language_id');
        });

        Schema::drop('languages');
    }
}
