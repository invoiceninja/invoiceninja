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
        if(!Gateway::find(65))
        {

            $fields =  new \stdClass;
            $fields->apiKey  = "";
            $fields->callbackSecret = "";

            $gateway = new Gateway;
            $gateway->id = 65;
            $gateway->name = 'Blockonomics';
            $gateway->key = 'wbhf02us6owgo7p4nfjd0ymssdshks4d';
            $gateway->provider = 'Blockonomics';
            $gateway->is_offsite = false;
            $gateway->fields = \json_encode($fields);


            $gateway->visible = true;
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
