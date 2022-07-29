<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSmsVerificationToHostedAccount extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->text('account_sms_verification_code')->nullable();
            $table->text('account_sms_verification_number')->nullable();
            $table->boolean('account_sms_verified')->default(0);
        });

        App\Models\Account::query()->cursor()->each(function ($account){

            $account->account_sms_verified = true;
            $account->save();

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
}
