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
        
        Language::unguard();

        $language = Language::find(40);

        if (! $language) {
            Language::create(['id' => 40, 'name' => 'French - Swiss', 'locale' => 'fr_CH']);
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
};
