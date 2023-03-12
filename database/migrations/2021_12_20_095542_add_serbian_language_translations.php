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
        $serbian = ['id' => 33, 'name' => 'Serbian', 'locale' => 'sr'];

        Language::unguard();
        Language::create($serbian);
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
