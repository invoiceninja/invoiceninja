<?php

use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $gateway = new Gateway();
        $gateway->id = 58;
        $gateway->name = 'Razorpay';
        $gateway->key = 'hxd6gwg3ekb9tb3v9lptgx1mqyg69zu9';
        $gateway->provider = 'Razorpay';
        $gateway->is_offsite = false;

        $configuration = new \stdClass;
        $configuration->apiKey = '';
        $configuration->apiSecret = '';

        $gateway->fields = \json_encode($configuration);
        $gateway->visible = true;
        $gateway->site_url = 'https://razorpay.com';
        $gateway->default_gateway_type_id = GatewayType::HOSTED_PAGE;
        $gateway->save();
    }
};
