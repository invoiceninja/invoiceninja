<?php

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
        Schema::table('company_gateways', function (Blueprint $table) {
            $table->boolean('require_custom_value1')->default(false);
            $table->boolean('require_custom_value2')->default(false);
            $table->boolean('require_custom_value3')->default(false);
            $table->boolean('require_custom_value4')->default(false);
        });
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
