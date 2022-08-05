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
            
        Schema::table('bank_integration', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');

            $table->string('account_type')->nullable();
            // $table->bigInteger('bank_account_id'); //providerAccountId
            // $table->bigInteger('bank_id'); //providerId
            $table->text('bank_name'); //providerName
            $table->text('account_name')->nullable(); //accountName
            $table->text('account_number')->nullable(); //accountNumber
            $table->text('account_status')->nullable(); //accountStatus
            $table->text('account_type')->nullable(); //CONTAINER
            $table->decimal('balance', 20, 6)->default(0); //currentBalance.amount
            $table->text('currency')->nullable(); //currentBalance.currency

            $table->
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
