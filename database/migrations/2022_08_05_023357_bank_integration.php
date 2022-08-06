<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

            $table->text('provider_bank_name'); //providerName ie Chase
            $table->bigInteger('bank_account_id'); //id
            $table->text('bank_account_name')->nullable(); //accountName
            $table->text('bank_account_number')->nullable(); //accountNumber
            $table->text('bank_account_status')->nullable(); //accountStatus
            $table->text('bank_account_type')->nullable(); //CONTAINER
            $table->decimal('balance', 20, 6)->default(0); //currentBalance.amount
            $table->text('currency')->nullable(); //currentBalance.currency

            $table->timestamps(6);
            $table->softDeletes('deleted_at', 6);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->text('bank_integration_account_id')->nullable();
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
