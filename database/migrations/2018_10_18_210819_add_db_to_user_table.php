<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDbToUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('db', 100);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('db', 100);
        });

        Schema::table('users', function (Blueprint $table){
            $table->dropColumn('confirmed');
            $table->dropColumn('registered');
        });

        Schema::table('contacts', function (Blueprint $table){
            $table->dropColumn('confirmed');
            $table->dropColumn('registered');

        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('db');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('db');
        });

        Schema::table('users', function (Blueprint $table){
            $table->boolean('confirmed');
            $table->boolean('registered');
        });

        Schema::table('contacts', function (Blueprint $table){
            $table->boolean('confirmed');
            $table->boolean('registered');

        });
    }
}
