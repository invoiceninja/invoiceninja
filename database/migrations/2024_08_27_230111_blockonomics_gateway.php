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
        if(!Gateway::find(64))
        {

            $BLOCKONOMICS_BASE_URL = 'https://www.blockonomics.co';
            $BLOCKONOMICS_GET_CALLBACKS_URL = $BLOCKONOMICS_BASE_URL . '/api/address?&no_balance=true&only_xpub=true&get_callback=true';
            $fields =  new \stdClass;
            $fields->apiKey  = "";
            // insert call to get callback url and set callback url based on response
            $fields->callbackUrl = config('ninja.app_url');
            $fields->callbackSecret = md5(uniqid(rand(), true));

            $gateway = new Gateway;
            $gateway->id = 64;
            $gateway->name = 'Blockonomics';
            $gateway->key = 'wbhf02us6owgo7p4nfjd0ymssdshks4d';
            $gateway->provider = 'Blockonomics';
            $gateway->is_offsite = true;
            $gateway->fields = \json_encode($fields);


            $gateway->visible = 1;
            $gateway->site_url = 'https://blockonomics.co';
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