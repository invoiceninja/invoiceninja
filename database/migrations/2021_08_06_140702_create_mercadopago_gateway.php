<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

class CreateMercadopagoGateway extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $gateway = new Gateway();
        $gateway->name = 'Mercado Pago';
        $gateway->key = Str::lower(Str::random(32));
        $gateway->provider = 'MercadoPago';
        $gateway->is_offsite = false;
        $gateway->fields = json_encode(['publicKey' => '', 'accessToken' => '']);
        $gateway->visible = true;
        $gateway->site_url = 'https://www.mercadopago.com.br/developers/pt/guides/online-payments/checkout-api/introduction';
        $gateway->default_gateway_type_id = 1;
        $gateway->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Gateway::where('provider', 'MercadoPago')->delete();
    }
}
