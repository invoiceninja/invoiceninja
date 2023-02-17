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
        Schema::create('bank_integrations', function (Blueprint $table) {
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

        Schema::table('accounts', function (Blueprint $table) {
            $table->text('bank_integration_account_id')->nullable();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('bank_integration_id');
            $table->unsignedBigInteger('transaction_id')->index();
            $table->decimal('amount', 20, 6)->default(0);
            $table->string('currency_code')->nullable();
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('account_type')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->unsignedInteger('ninja_category_id')->nullable();
            $table->string('category_type')->index();
            $table->string('base_type')->index();
            $table->date('date')->nullable();
            $table->unsignedBigInteger('bank_account_id');
            $table->text('description')->nullable();
            $table->text('invoice_ids')->default('');
            $table->unsignedInteger('expense_id')->nullable();
            $table->unsignedInteger('vendor_id')->nullable();
            $table->unsignedInteger('status_id')->default(1); //unmatched / matched / converted
            $table->boolean('is_deleted')->default(0);

            $table->timestamps(6);

            $table->softDeletes('deleted_at', 6);
            $table->foreign('bank_integration_id')->references('id')->on('bank_integrations')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->unsignedInteger('bank_category_id')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable();
        });

        Schema::table('expenses', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
