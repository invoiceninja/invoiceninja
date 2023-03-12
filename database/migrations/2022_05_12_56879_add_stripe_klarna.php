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
        $pt = PaymentType::find(47);

        if (!$pt) {
            $type = new PaymentType();
            $type->id = 47;
            $type->name = 'Klarna';
            $type->gateway_type_id = GatewayType::KLARNA;
            $type->save();
        }


        $pt = PaymentType::find(48);

        if (!$pt) {
            $type = new PaymentType();
            $type->id = 48;
            $type->name = 'Interac E-Transfer';
            $type->save();
        }

        $gt = GatewayType::find(23);

        if (!$gt) {
            $type = new GatewayType();
            $type->id = 23;
            $type->alias = 'klarna';
            $type->name = 'Klarna';
            $type->save();
        }
    }
};
