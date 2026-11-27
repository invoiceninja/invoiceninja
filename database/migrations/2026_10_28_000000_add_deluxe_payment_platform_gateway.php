<?php

use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('gateways') && ! Gateway::find(70)) {
            $configuration = new \stdClass();
            $configuration->partnerToken = '';
            $configuration->clientId = '';
            $configuration->clientSecret = '';
            $configuration->testMode = false;

            $gateway = new Gateway();
            $gateway->id = 70;
            $gateway->name = 'Deluxe Payment Platform';
            $gateway->key = '26e7aed2062f96138650d6e0e133be8b';
            $gateway->provider = 'DeluxePaymentPlatform';
            $gateway->is_offsite = false;
            $gateway->sort_order = 32;
            $gateway->fields = \json_encode($configuration);
            $gateway->visible = true;
            $gateway->site_url = 'https://developer.deluxe.com';
            $gateway->default_gateway_type_id = GatewayType::CREDIT_CARD;
            $gateway->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Gateway::where('key', '26e7aed2062f96138650d6e0e133be8b')->delete();
    }
};
