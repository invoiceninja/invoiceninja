<?php

use App\Models\GatewayType;
use App\Models\PaymentType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $pt = PaymentType::find(49);

        if(!$pt) {
            $type = new PaymentType();
            $type->id = 49;
            $type->name = 'BACS';
            $type->gateway_type_id = GatewayType::BACS;
            $type->save();
        }

        $gt = GatewayType::find(24);

        if(!$gt) {
            $type = new GatewayType();
            $type->id = 24;
            $type->alias = 'bacs';
            $type->name = 'BACS';
            $type->save();
        }
    }
};
