<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use App\Models\GatewayType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('gateways')) {
            $gateway = Gateway::find(66);

            if (!$gateway) {
                $gateway = new Gateway();
                $gateway->id = 66;
                $gateway->name = 'Helcim';
                $gateway->key = 'ca3b3f7e4be811c96a8a1f4cafe2a97f';
                $gateway->provider = 'Helcim';
                $gateway->is_offsite = false;
                $gateway->sort_order = 28;
                
                $configuration = new \stdClass();
                $configuration->apiToken = '';
                $configuration->testMode = false;
                
                $gateway->fields = \json_encode($configuration);
                $gateway->visible = true;
                $gateway->site_url = 'https://www.helcim.com';
                $gateway->default_gateway_type_id = GatewayType::CREDIT_CARD;
                $gateway->save();
            }
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