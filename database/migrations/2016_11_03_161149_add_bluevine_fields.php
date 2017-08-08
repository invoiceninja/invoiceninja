<?php

use Illuminate\Database\Migrations\Migration;

class AddBluevineFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->enum('bluevine_status', ['ignored', 'signed_up'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('bluevine_status');
        });
    }
}
