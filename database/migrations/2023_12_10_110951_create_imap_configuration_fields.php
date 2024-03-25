<?php

use App\Models\Company;
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
            $table->boolean("inbound_mailbox_active")->default(true);
            $table->string("inbound_mailbox")->nullable();
            $table->boolean("inbound_mailbox_allow_company_users")->default(false);
            $table->boolean("inbound_mailbox_allow_vendors")->default(false);
            $table->boolean("inbound_mailbox_allow_clients")->default(false);
            $table->boolean("inbound_mailbox_allow_unknown")->default(false);
            $table->text("inbound_mailbox_whitelist_domains")->nullable();
            $table->text("inbound_mailbox_whitelist_senders")->nullable();
            $table->text("inbound_mailbox_blacklist_domains")->nullable();
            $table->text("inbound_mailbox_blacklist_senders")->nullable();
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->string("invoicing_email")->nullable();
            $table->string("invoicing_domain")->nullable();
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
