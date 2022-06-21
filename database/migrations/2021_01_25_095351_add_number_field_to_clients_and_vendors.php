<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
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
            $table->unique(['company_id', 'number']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->string('id_number')->nullable();
            $table->unique(['company_id', 'number']);
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
