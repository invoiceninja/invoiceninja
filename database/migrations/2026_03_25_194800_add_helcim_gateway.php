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
            // If Helcim was previously installed at id=67 (before LawPay/Payware were added
            // upstream), migrate it to id=68 to avoid the collision.
            $existing67 = Gateway::find(67);
            if ($existing67 && $existing67->key === 'ca3b3f7e4be811c96a8a1f4cafe2a97f') {
                // This is the old Helcim record at the wrong id — remove it so we can
                // recreate it at id=68 below.
                $existing67->delete();
            }

            $gateway = Gateway::find(68);

            if (!$gateway) {
                $gateway = new Gateway();
                $gateway->id = 68;
                $gateway->name = 'Helcim';
                $gateway->key = 'ca3b3f7e4be811c96a8a1f4cafe2a97f';
                $gateway->provider = 'Helcim';
                $gateway->is_offsite = false;
                $gateway->sort_order = 30;

                $configuration = new \stdClass();
                $configuration->apiToken = '';
                $configuration->webhookVerifierToken = '';

                $gateway->fields = \json_encode($configuration);
                $gateway->visible = true;
                $gateway->site_url = 'https://www.helcim.com';
                $gateway->default_gateway_type_id = GatewayType::CREDIT_CARD;
                $gateway->save();
            } else {
                // Gateway already exists — ensure the fields include webhookVerifierToken
                // and do not include the defunct testMode field.
                $configuration = new \stdClass();
                $configuration->apiToken = '';
                $configuration->webhookVerifierToken = '';

                $gateway->fields = \json_encode($configuration);
                $gateway->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Gateway::where('key', 'ca3b3f7e4be811c96a8a1f4cafe2a97f')->delete();
    }
};
