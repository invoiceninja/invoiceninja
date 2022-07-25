<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Gateway;
use App\Utils\Ninja;
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
        if (Ninja::isHosted()) {
            $stripe_connect = Gateway::find(56);

            if ($stripe_connect) {
                $stripe_connect->fields = '{"account_id":""}';
                $stripe_connect->save();
            }
        }

        Company::cursor()->each(function ($company) {
            $company->update(['markdown_email_enabled' => true]);
        });

        $chf = Currency::find(17);

        if ($chf) {
            $chf->symbol = 'CHF';
            $chf->save();
        }

        if (Ninja::isSelfHost()) {
            $gateway = Gateway::find(20);

            if ($gateway) {
                $gateway->fields = '{"publishableKey":"","apiKey":"","appleDomainVerification":""}';
                $gateway->save();
            }
        }
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
