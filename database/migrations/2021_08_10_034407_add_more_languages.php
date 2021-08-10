<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMoreLanguages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Language::unguard();

        $language = Language::where(['locale' => 'ar'])->first();

        if(!$language){

            $language = new Language();
            $language->id = 29;
            $language->name = 'Arabic';
            $language->save();
        }

       $language = Language::where(['locale' => 'fa'])->first();

        if(!$language){

            $language = new Language();
            $language->id = 30;
            $language->name = 'Persian';
            $language->save();
        }

       $language = Language::where(['locale' => 'lv_LV'])->first();

        if(!$language){

            $language = new Language();
            $language->id = 31;
            $language->name = 'Latvian';
            $language->save();
        }
        

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
