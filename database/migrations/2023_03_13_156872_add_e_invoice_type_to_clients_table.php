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

        Schema::table('clients', function (Blueprint $table) {
            $table->string('routing_id')->default(null)->nullable();
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('enable_e_invoice')->default(false);
            $table->string('e_invoice_type')->default("EN16931");
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
