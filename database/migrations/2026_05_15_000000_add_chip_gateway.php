<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {

    public function up(): void
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        if (! Gateway::find(68)) {
            $fields = new \stdClass;
            $fields->apiKey = '';
            $fields->brandId = '';

            $gateway = new Gateway();
            $gateway->id = 68;
            $gateway->name = 'CHIP';
            $gateway->key = 'c7a8e2f1b4d90635a3f8e1c9b2d4a6e0';
            $gateway->provider = 'ChipInAsia';
            $gateway->is_offsite = true;
            $gateway->fields = \json_encode($fields);
            $gateway->visible = true;
            $gateway->sort_order = 30;
            $gateway->site_url = 'https://notes.chip-in.asia/s/faq/p/Qwsatm6PeN';
            $gateway->default_gateway_type_id = 14;
            $gateway->save();
        }

        \Illuminate\Database\Eloquent\Model::reguard();
    }

    public function down(): void
    {
        //
    }
};
