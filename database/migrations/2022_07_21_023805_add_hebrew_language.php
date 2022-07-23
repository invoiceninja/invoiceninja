<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHebrewLanguage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Language::unguard();


        if(!Language::find(33)) {
            
            $serbian = ['id' => 33, 'name' => 'Serbian', 'locale' => 'sr'];
            Language::create($serbian);

        }

        if(!Language::find(34)) {
            
            $slovak = ['id' => 34, 'name' => 'Slovak', 'locale' => 'sk'];
            Language::create($slovak);

        }

        if(!Language::find(35)) {
            
            $estonia = ['id' => 35, 'name' => 'Estonian', 'locale' => 'et'];
            Language::create($estonia);

        }

        if(!Language::find(36)) {
            
            $bulgarian = ['id' => 36, 'name' => 'Bulgarian', 'locale' => 'bg'];
            Language::create($bulgarian);

        }

        if(!Language::find(37)) {
            
            $hebrew = ['id' => 37, 'name' => 'Hebrew', 'locale' => 'he'];
            Language::create($hebrew);

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
