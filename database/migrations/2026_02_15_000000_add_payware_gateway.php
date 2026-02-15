<?php

use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        if (! GatewayType::find(30)) {
            $gatewayType = new GatewayType();
            $gatewayType->id = 30;
            $gatewayType->alias = 'mobile_payment';
            $gatewayType->name = 'Mobile payment';
            $gatewayType->save();
        }

        if (! Gateway::find(66)) {
            $fields = new \stdClass;
            $fields->partnerId = '';
            $fields->vposId = '';
            $fields->paywarePublicKey = '';
            $fields->testMode = false;

            $gateway = new Gateway();
            $gateway->id = 66;
            $gateway->name = 'payware';
            $gateway->key = 'b0a6294fca4488c2bab58f3e11e3c623';
            $gateway->provider = 'Payware';
            $gateway->is_offsite = false;
            $gateway->fields = \json_encode($fields);
            $gateway->visible = 1;
            $gateway->sort_order = 28;
            $gateway->site_url = 'https://payware.eu';
            $gateway->default_gateway_type_id = 30;
            $gateway->save();
        }
    }

    public function down(): void
    {
        Gateway::where('id', 66)->delete();
        GatewayType::where('id', 30)->delete();
    }
};
