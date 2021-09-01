<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

class CreateRecebeAquiGateway extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $gateway = new Gateway();
        $gateway->name = 'Recebe Aqui';
        $gateway->key = Str::lower(Str::random(32));
        $gateway->provider = 'RecebeAqui';
        $gateway->is_offsite = false;
        $gateway->fields = json_encode(['tokenCliente' => '']);
        $gateway->visible = 1;
        $gateway->site_url = 'https://recebeaqui.com/InfograficoAPI';
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
        Gateway::where('provider', 'RecebeAqui')->delete();
    }
}
