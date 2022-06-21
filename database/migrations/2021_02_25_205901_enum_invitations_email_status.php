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
        Schema::table('invoice_invitations', function (Blueprint $table) {
            $table->enum('email_status', ['delivered', 'bounced', 'spam'])->nullable();
        });

        Schema::table('quote_invitations', function (Blueprint $table) {
            $table->enum('email_status', ['delivered', 'bounced', 'spam'])->nullable();
        });

        Schema::table('credit_invitations', function (Blueprint $table) {
            $table->enum('email_status', ['delivered', 'bounced', 'spam'])->nullable();
        });

        Schema::table('recurring_invoice_invitations', function (Blueprint $table) {
            $table->enum('email_status', ['delivered', 'bounced', 'spam'])->nullable();
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
