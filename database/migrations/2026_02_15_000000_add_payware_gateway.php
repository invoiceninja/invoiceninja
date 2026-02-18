<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {

    public function up(): void
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        if (! Gateway::find(66)) {
            $fields = new \stdClass;
            $fields->partnerId = '';
            $fields->vposId = '';
            $fields->paywarePublicKey = '';
            $fields->testMode = false;
            $fields->timeToLive = 600;

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
        //
    }
};
