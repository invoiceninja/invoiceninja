<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $estonia = ['id' => 35, 'name' => 'Estonian', 'locale' => 'et'];

        Language::unguard();
        Language::create($estonia);
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
