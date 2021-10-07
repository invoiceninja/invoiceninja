<?php

use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

class Razorpay extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $gateway = new Gateway();
        $gateway->name = 'Razorpay';
        $gateway->key = Str::lower(Str::random(32));
        $gateway->provider = 'Razorpay';
        $gateway->is_offsite = false;
        $gateway->fields = new \stdClass;
        $gateway->visible = true;
        $gateway->site_url = 'https://razorpay.com';
        $gateway->default_gateway_type_id = GatewayType::HOSTED_PAGE;
        $gateway->save();
    }
}
