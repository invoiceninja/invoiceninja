<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNumberFieldToClientsAndVendors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('id_number', 'number');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->renameColumn('id_number', 'number');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('id_number')->nullable();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('id_number')->nullable();
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
}
