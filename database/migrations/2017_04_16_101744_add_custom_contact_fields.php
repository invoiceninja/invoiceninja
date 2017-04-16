<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomContactFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->string('custom_contact_label1')->nullable();
            $table->string('custom_contact_label2')->nullable();
        });

        Schema::table('contacts', function ($table) {
            $table->string('custom_value1')->nullable();
            $table->string('custom_value2')->nullable();
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
            $table->dropColumn('custom_contact_label1');
            $table->dropColumn('custom_contact_label2');
        });

        Schema::table('contacts', function ($table) {
            $table->dropColumn('custom_value1');
            $table->dropColumn('custom_value2');
        });
    }
}
