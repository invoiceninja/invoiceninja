<?php

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
                $stripe_connect->fields = '{"account_id":"", "appleDomainVerification":""}';
                $stripe_connect->save();
            }
        }

        $stripe_connect = Gateway::find(20);

        if ($stripe_connect) {
            $stripe_connect->fields = '{"account_id":"", "appleDomainVerification":""}';
            $stripe_connect->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
};
