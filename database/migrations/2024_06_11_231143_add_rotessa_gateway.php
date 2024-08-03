<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Gateway;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Model::unguard();

        if(!Gateway::find(63)) {
            $configuration = new \stdClass;
            $configuration->apiKey = '';
            $configuration->testMode =  true;

            $gateway = new Gateway();
            $gateway->id = 63;
            $gateway->name = 'Rotessa'; 
            $gateway->key = '91be24c7b792230bced33e930ac61676'; 
            $gateway->provider = 'Rotessa';
            $gateway->is_offsite = true;
            $gateway->fields = \json_encode($configuration);
            $gateway->visible = 1;
            $gateway->site_url = "https://rotessa.com";
            $gateway->default_gateway_type_id = 2;
            $gateway->save();
        }
    }
};
