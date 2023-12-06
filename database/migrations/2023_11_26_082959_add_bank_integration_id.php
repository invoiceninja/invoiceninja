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
        });

        // migrate old account to be used with yodlee
        BankIntegration::query()->whereNull('integration_type')->whereNotNull('account_id')->cursor()->each(function ($bank_integration) {
            $bank_integration->integration_type = BankIntegration::INTEGRATION_TYPE_YODLEE;
            $bank_integration->save();
        });

        // MAYBE migration of account->bank_account_id etc
        Schema::table('accounts', function (Blueprint $table) {
            $table->renameColumn('bank_integration_account_id', 'bank_integration_yodlee_account_id');
            $table->string('bank_integration_nordigen_secret_id')->nullable();
            $table->string('bank_integration_nordigen_secret_key')->nullable();
        });

        // TODO: assign requisitions, to determine, which requisitions belong to which account and which can be leaned up, when necessary
        Schema::create('bank_integration_nordigen_requisitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');

            $table->text('provider_name'); //providerName ie Chase
            $table->bigInteger('provider_id'); //id of the bank
            $table->bigInteger('bank_account_id'); //id
            $table->text('bank_account_name')->nullable(); //accountName
            $table->text('bank_account_number')->nullable(); //accountNumber
            $table->text('bank_account_status')->nullable(); //accountStatus
            $table->text('bank_account_type')->nullable(); //CONTAINER
            $table->decimal('balance', 20, 6)->default(0); //currentBalance.amount
            $table->text('currency')->nullable(); //currentBalance.currency
            $table->text('nickname')->default(''); //accountName
            $table->date('from_date')->nullable();

            $table->boolean('is_deleted')->default(0);

            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
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
