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

        \DB::statement('CREATE INDEX client_hash_idx ON clients (client_hash(20));');
        \DB::statement('CREATE INDEX client_contact_key_idx ON client_contacts (contact_key(20));');
        \DB::statement('CREATE INDEX vendor_contact_key_idx ON vendor_contacts (contact_key(20));');

        Schema::table('client_contacts', function (Blueprint $table) {
            $table->index('email');
        });

        Schema::table('vendor_contacts', function (Blueprint $table) {
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
