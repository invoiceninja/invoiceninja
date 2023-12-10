<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('company_user', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->mediumText('react_settings')->nullable();
        });

        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');

        Schema::table('schedulers', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->integer('remaining_cycles')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
