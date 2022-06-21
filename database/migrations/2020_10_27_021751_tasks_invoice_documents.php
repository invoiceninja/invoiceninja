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
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('invoice_documents')->default(0);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('show_tasks_table')->default();
        });

        Schema::table('invoice_invitations', function (Blueprint $table) {
            $table->text('signature_ip')->nullable();
        });

        Schema::table('quote_invitations', function (Blueprint $table) {
            $table->text('signature_ip')->nullable();
        });

        Schema::table('credit_invitations', function (Blueprint $table) {
            $table->text('signature_ip')->nullable();
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
};
