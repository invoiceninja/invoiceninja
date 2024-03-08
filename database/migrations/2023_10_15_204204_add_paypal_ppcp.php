<?php

use App\Models\Gateway;
use App\Utils\Ninja;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        if(!Gateway::find(61) && Ninja::isHosted()) {

            $fields = new \stdClass;
            $fields->testMode = false;
            
            $paypal = new Gateway;
            $paypal->id = 61;
            $paypal->name = 'PayPal Platform';
            $paypal->key = '80af24a6a691230bbec33e930ab40666';
            $paypal->provider = 'PayPal_PPCP';
            $paypal->is_offsite = false;
            $paypal->fields = \json_encode($fields);
            $paypal->visible = 1;
            $paypal->site_url = 'https://www.paypal.com/';
            $paypal->save();
        
        }

        Gateway::whereIn('id', [60, 15, 49])->update(['visible' => 0]);
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
