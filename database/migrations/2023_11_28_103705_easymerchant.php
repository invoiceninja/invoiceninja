<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $check_gateway = Gateway::find(63);
            
        if(!$check_gateway){
            $gateway = new Gateway();
            $gateway->id = 63;
            $gateway->name = 'Easymerchant';
            $gateway->key = "yufm0y5xnyyvdw3kptqlsn83dg3q6giw";
            $gateway->provider = 'Easymerchant';
            $gateway->is_offsite = true;

            $configuration = ['test_api_key' => 'testkey', 'test_api_secret' => 'testsecret', 'api_key' => 'livekey', 'api_secret' => 'livesecret', 'testMode' => true, 'test_url' => 'https://stage-api.stage-easymerchant.io/api/v1', 'production_url' => 'https://api.lyfepay.io/api/v1'];

            $gateway->fields = \json_encode($configuration);
            $gateway->site_url = 'https://api.lyfepay.io/';
            $gateway->default_gateway_type_id = 1;//GatewayType::CREDIT_CARD;
            $gateway->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
