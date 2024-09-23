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
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean("expense_mailbox_active")->default(false);
            $table->string("expense_mailbox")->nullable();
            $table->boolean("inbound_mailbox_allow_company_users")->default(false);
            $table->boolean("inbound_mailbox_allow_vendors")->default(false);
            $table->boolean("inbound_mailbox_allow_clients")->default(false);
            $table->boolean("inbound_mailbox_allow_unknown")->default(false);
            $table->text("inbound_mailbox_whitelist")->nullable();
            $table->text("inbound_mailbox_blacklist")->nullable();
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
