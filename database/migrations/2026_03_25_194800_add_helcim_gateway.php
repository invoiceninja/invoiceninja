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
            // If Helcim was previously installed at id=67 or id=68 (before LawPay/Payware/CHIP
            // were added upstream), remove the stale record so it can be recreated at id=69.
            foreach ([67, 68] as $stale_id) {
                $existing = Gateway::find($stale_id);
                if ($existing && $existing->key === 'ca3b3f7e4be811c96a8a1f4cafe2a97f') {
                    // This is an old Helcim record at the wrong id — remove it so we can
                    // recreate it at id=69 below.
                    $existing->delete();
                }
            }

            $gateway = Gateway::find(69);

            if (!$gateway) {
                $gateway = new Gateway();
                $gateway->id = 69;
                $gateway->name = 'Helcim';
                $gateway->key = 'ca3b3f7e4be811c96a8a1f4cafe2a97f';
                $gateway->provider = 'Helcim';
                $gateway->is_offsite = false;
                $gateway->sort_order = 31;

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
