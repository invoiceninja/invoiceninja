<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        if(!Gateway::find(60)) {

            $fields = new \stdClass;
            $fields->clientId = "";
            $fields->secret = "";
            $fields->testMode = false;

            $paypal = new Gateway;
            $paypal->id = 60;
            $paypal->name = 'PayPal REST';
            $paypal->key = '80af24a6a691230bbec33e930ab40665';
            $paypal->provider = 'PayPal_Rest';
            $paypal->is_offsite = false;
            $paypal->fields = \json_encode($fields);
            $paypal->visible = 1;
            $paypal->site_url = 'https://www.paypal.com/';
            $paypal->save();
        }
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
};
