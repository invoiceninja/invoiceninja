<?php

use Illuminate\Database\Migrations\Migration;
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
        if(!Gateway::find(62))
        {
            $gateway = new Gateway;
            $gateway->id = 62;
            $gateway->name = 'BTCPay';
            $gateway->key = 'vpyfbmdrkqcicpkjqdusgjfluebftuva';
            $gateway->provider = 'BTCPay';
            $gateway->is_offsite = true;

            $btcpayFieds =  new \stdClass;
            $btcpayFieds->btcpayUrl  = "";
            $btcpayFieds->apiKey  = "";
            $btcpayFieds->storeId = "";
            $btcpayFieds->webhookSecret = "";
            $gateway->fields = \json_encode($btcpayFieds);


            $gateway->visible = true;
            $gateway->site_url = 'https://btcpayserver.org';
            $gateway->default_gateway_type_id = GatewayType::CRYPTO;
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