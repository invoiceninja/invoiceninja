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
        // Schema::table('companies', function (Blueprint $table) {
        //     $table->boolean("expense_mailbox_active")->default(false);
        //     $table->string("expense_mailbox")->nullable();
        //     $table->boolean("inbound_mailbox_allow_company_users")->default(false);
        //     $table->boolean("inbound_mailbox_allow_vendors")->default(false);
        //     $table->boolean("inbound_mailbox_allow_clients")->default(false);
        //     $table->boolean("inbound_mailbox_allow_unknown")->default(false);
        //     $table->text("inbound_mailbox_whitelist")->nullable();
        //     $table->text("inbound_mailbox_blacklist")->nullable();
        // });
    
            
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'expense_mailbox_active')) {
                $table->boolean("expense_mailbox_active")->default(false);
            }
            if (!Schema::hasColumn('companies', 'expense_mailbox')) {
                $table->string("expense_mailbox")->nullable();
            }
            if (!Schema::hasColumn('companies', 'inbound_mailbox_allow_company_users')) {
                $table->boolean("inbound_mailbox_allow_company_users")->default(false);
            }
            if (!Schema::hasColumn('companies', 'inbound_mailbox_allow_vendors')) {
                $table->boolean("inbound_mailbox_allow_vendors")->default(false);
            }
            if (!Schema::hasColumn('companies', 'inbound_mailbox_allow_clients')) {
                $table->boolean("inbound_mailbox_allow_clients")->default(false);
            }
            if (!Schema::hasColumn('companies', 'inbound_mailbox_allow_unknown')) {
                $table->boolean("inbound_mailbox_allow_unknown")->default(false);
            }
            if (!Schema::hasColumn('companies', 'inbound_mailbox_whitelist')) {
                $table->text("inbound_mailbox_whitelist")->nullable();
            }
            if (!Schema::hasColumn('companies', 'inbound_mailbox_blacklist')) {
                $table->text("inbound_mailbox_blacklist")->nullable();
            }
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
