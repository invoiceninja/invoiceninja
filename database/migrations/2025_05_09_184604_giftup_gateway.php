<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        $gateway = new Gateway;
        $gateway->name = 'Gift Up!';
        $gateway->key = 'giftup';
        $gateway->provider = 'GiftUp';
        $gateway->is_offsite = false;
        $gateway->fields = json_encode([
            'api_key' => '',
            'test_mode' => true
        ]);
        $gateway->visible = true;
        $gateway->site_url = 'https://giftup.com';
        $gateway->default_gateway_type_id = GatewayType::CUSTOM;
        $gateway->save();

        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
