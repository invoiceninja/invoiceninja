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
        Schema::table('company', function (Blueprint $table) {
            $table->boolean("expense_import")->default(true);
            $table->string("expense_mailbox")->nullable();
        });
        Company::query()->cursor()->each(function ($company) { // TODO: @turbo124 check migration on staging environment with real data to ensure, this works as exspected
            $company->expense_mailbox = config('ninja.inbound_expense.webhook.mailbox_template') != '' ?
                str_replace('{{company_key}}', $company->company_key, config('ninja.inbound_expense.webhook.mailbox_template')) : null;

            $company->save();
        });
        Schema::table('vendor', function (Blueprint $table) {
            $table->string("expense_sender_email")->nullable();
            $table->string("expense_sender_domain")->nullable();
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
