<?php

use App\Models\Language;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRussianLang extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $russian = ['id' => 29, 'name' => 'Russian (Russia)', 'locale' => 'ru_RU'];

        Language::unguard();
        Language::create($russian);

        Schema::table('users', function (Blueprint $table) {
            $table->integer('default_password_timeout')->default(30);
        });

        User::whereNotNull('id')->update(['default_password_timeout' => 30]);

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
