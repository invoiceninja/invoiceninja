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
            $table->index([\DB::raw('client_hash(20)')]);
        });


        Schema::table('client_contacts', function (Blueprint $table) {
            $table->index([\DB::raw('contact_key(20)')]);
            $table->index('email');
        });

        Schema::table('vendor_contacts', function (Blueprint $table) {
            $table->index([\DB::raw('contact_key(20)')]);
            $table->index('email');
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
