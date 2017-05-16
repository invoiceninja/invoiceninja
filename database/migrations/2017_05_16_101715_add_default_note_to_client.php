<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDefaultNoteToClient extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function ($table) {
            $table->text('public_notes')->nullable();
        });

        Schema::table('invoices', function ($table) {
            $table->text('private_notes')->nullable();
        });

        Schema::table('payments', function ($table) {
            $table->text('private_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function ($table) {
            $table->dropColumn('public_notes');
        });

        Schema::table('invoices', function ($table) {
            $table->dropColumn('private_notes');
        });

        Schema::table('payments', function ($table) {
            $table->dropColumn('private_notes');
        });
    }
}
