<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('company', function (Blueprint $table) {
            $table->string("expense_mailbox_imap_host")->nullable();
            $table->string("expense_mailbox_imap_port")->nullable();
            $table->string("expense_mailbox_imap_user")->nullable();
            $table->string("expense_mailbox_imap_password")->nullable();
        });
        Schema::table('vendor', function (Blueprint $table) {
            $table->string("expense_sender_email")->nullable();
            $table->string("expense_sender_url")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
