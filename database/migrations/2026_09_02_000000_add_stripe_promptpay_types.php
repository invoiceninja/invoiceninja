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
        $pt = PaymentType::find(PaymentType::PROMPTPAY);

        if(!$pt) {
            $type = new PaymentType();
            $type->id = PaymentType::PROMPTPAY;
            $type->name = 'PromptPay';
            $type->gateway_type_id = GatewayType::PROMPTPAY;
            $type->save();
        }

        $gt = GatewayType::find(GatewayType::PROMPTPAY);

        if(!$gt) {
            $type = new GatewayType();
            $type->id = GatewayType::PROMPTPAY;
            $type->alias = 'promptpay';
            $type->name = 'PromptPay';
            $type->save();
        }
    }
};
