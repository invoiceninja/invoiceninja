<?php

use App\Models\Gateway;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
                
        Model::unguard();

        $fields = new \stdClass();

        $fields->publicKey = '';
        $fields->secretKey = '';
        $fields->testMode = false;
        $fields->gatewayId = '';
        $fields->amex = false;
        $fields->ausbc = false;
        $fields->discover = false;
        $fields->japcb = false;
        $fields->laser = false;
        $fields->mastercard = true;
        $fields->solo = false;
        $fields->visa = true;
        $fields->visa_white = false;


        if($gateway = Gateway::find(64)){
            $gateway->fields = json_encode($fields);
            $gateway->save();
        }else{
            
        $powerboard = new Gateway();
        $powerboard->id = 64;
        $powerboard->name = 'CBA PowerBoard';
        $powerboard->provider = 'CBAPowerBoard';
        $powerboard->key = 'b67581d804dbad1743b61c57285142ad';
        $powerboard->sort_order = 4543;
        $powerboard->is_offsite = false;
        $powerboard->visible = true;
        $powerboard->fields = json_encode($fields);
        $powerboard->save();

        }
        
        Schema::table("company_gateways", function (\Illuminate\Database\Schema\Blueprint $table){
            $table->text('settings')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
