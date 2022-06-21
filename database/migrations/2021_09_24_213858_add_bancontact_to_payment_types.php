<?php

use App\Models\GatewayType;
use App\Models\PaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $type = new PaymentType();

        $type->id = 36;
        $type->name = 'Bancontact';
        $type->gateway_type_id = GatewayType::BANCONTACT;

        $type->save();
    }
};
