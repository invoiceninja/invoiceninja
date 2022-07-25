<?php

use App\Models\Gateway;
use App\Utils\Ninja;
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
        if (Gateway::count() >= 1 && Ninja::isHosted()) {
            Gateway::whereIn('id', [49])->update(['visible' => true]);
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
