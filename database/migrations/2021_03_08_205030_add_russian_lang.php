<?php

use App\Models\Company;
use App\Models\Language;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

        Schema::table('companies', function (Blueprint $table) {
            $table->integer('default_password_timeout')->default(30);
        });

        Company::whereNotNull('id')->update(['default_password_timeout' => 30]);
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
};
