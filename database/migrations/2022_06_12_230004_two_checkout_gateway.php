<?php

use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TwoCheckoutGateway extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $fields = new \stdClass;
        $fields->baseUri = "";
        $fields->merchantCode = "";
        $fields->secretCode = "";
        $fields->insSecretWord = "";
        $fields->testMode = false;


        $gateway = new Gateway;
        $gateway->id = 59;
        $gateway->name = 'Two Checkout';
        $gateway->key = Str::lower(Str::random(32));
        $gateway->provider = 'TwoCheckout';
        $gateway->is_offsite = true;
        $gateway->fields = json_encode($fields);
        $gateway->visible = true;
        $gateway->site_url = 'https://www.2checkout.com/';
        $gateway->default_gateway_type_id = GatewayType::CREDIT_CARD;
        $gateway->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
