<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueConstraintsOnAllEntities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unique(['company_id', 'number']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unique(['company_id', 'number']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->unique(['company_id', 'number']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['company_id', 'number']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->unique(['company_id', 'number']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->unique(['company_id', 'number']);
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->string('number')->change();
            $table->unique(['company_id', 'number']);
        });

        Schema::table('recurring_invoice_invitations', function (Blueprint $table) {
            $table->unique(['client_contact_id', 'recurring_invoice_id'],'recur_invoice_client_unique');
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
