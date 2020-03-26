<?php

use Illuminate\Database\Migrations\Migration;

class AddSwapPostalCode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('countries', function ($table) {
            $table->boolean('swap_postal_code')->default(0);
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('show_item_taxes')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('countries', function ($table) {
            $table->dropColumn('swap_postal_code');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('show_item_taxes');
        });
    }
}
