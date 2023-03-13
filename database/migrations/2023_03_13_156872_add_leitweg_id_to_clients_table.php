<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->string('leitweg_idf')->default(null);
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('use_xinvoice')->default(false);
            $table->string('xinvoice_type')->default("EN16931");
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
