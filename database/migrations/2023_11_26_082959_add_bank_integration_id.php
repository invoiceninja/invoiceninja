<?php

use App\Models\Account;
use App\Models\BankIntegration;
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
        Schema::table('bank_integrations', function (Blueprint $table) {
            $table->string('integration_type')->nullable();
            $table->string('nordigen_account_id')->nullable();
            $table->string('nordigen_institution_id')->nullable();
        });

        // migrate old account to be used with yodlee
        BankIntegration::query()->whereNull('integration_type')->whereNotNull('account_id')->cursor()->each(function ($bank_integration) {
            $bank_integration->integration_type = BankIntegration::INTEGRATION_TYPE_YODLEE;
            $bank_integration->save();
        });

        // MAYBE migration of account->bank_account_id etc
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->string('participant')->nullable(); // iban, credit-card info or else
            $table->string('participant_name')->nullable(); // name
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
