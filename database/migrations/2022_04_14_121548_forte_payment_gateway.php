<?php

use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;

class FortePaymentGateway extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $fields = new \stdClass;
        $fields->testMode = false;
        $fields->apiLoginId = "";
        $fields->apiAccessId = "";
        $fields->secureKey = "";
        $fields->authOrganizationId = "";
        $fields->organizationId = "";
        $fields->locationId = "";

        $forte = new Gateway;
        $forte->id = 59;
        $forte->name = 'Forte';
        $forte->key = 'kivcvjexxvdiyqtj3mju5d6yhpeht2xs';
        $forte->provider = 'Forte';
        $forte->is_offsite = true;
        $forte->fields = \json_encode($fields);
        $forte->visible = 1;
        $forte->site_url = 'https://www.forte.net/';
        $forte->default_gateway_type_id = GatewayType::CREDIT_CARD;
        $forte->save();
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
