<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Model;
use App\Models\Gateway;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Model::unguard();

        if (!Gateway::find(66)) {
            $configuration = new \stdClass;
            $configuration->publicKey = '';
            $configuration->secretKey = '';
            $configuration->testMode = false;

            $gateway = new Gateway();
            $gateway->id = 66;
            $gateway->name = 'LawPay';
            $gateway->key = 'f4lafbnygsmkflagbqp7zqnfpgeoekdn';
            $gateway->provider = 'LawPay';
            $gateway->is_offsite = false;
            $gateway->fields = \json_encode($configuration);
            $gateway->visible = 0;
            $gateway->site_url = 'https://www.lawpay.com';
            $gateway->default_gateway_type_id = 1;
            $gateway->save();
        }
    }
};
