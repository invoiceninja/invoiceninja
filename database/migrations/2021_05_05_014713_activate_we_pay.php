<?php

use App\Models\Gateway;
use App\Utils\Ninja;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Gateway::count() >= 1 && Ninja::isHosted()) {
            Gateway::query()->whereIn('id', [49])->update(['visible' => true]);
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
