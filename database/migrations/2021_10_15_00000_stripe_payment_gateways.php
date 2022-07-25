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
        Schema::table('payment_types', function (Blueprint $table) {
            $type = new PaymentType();
            $type2 = new PaymentType();
            $type3 = new PaymentType();
            $type4 = new PaymentType();
            $type5 = new PaymentType();

            $type->id = 44;
            $type->name = 'ACSS';
            $type->gateway_type_id = GatewayType::ACSS;

            $type2->id = 43;
            $type2->name = 'BECS';
            $type2->gateway_type_id = GatewayType::BECS;

            $type3->id = 39;
            $type3->name = 'GiroPay';
            $type3->gateway_type_id = GatewayType::GIROPAY;

            $type4->id = 40;
            $type4->name = 'Przelewy24';
            $type4->gateway_type_id = GatewayType::PRZELEWY24;

            $type5->id = 41;
            $type5->name = 'EPS';
            $type5->gateway_type_id = GatewayType::EPS;

            $type->save();
            $type2->save();
            $type3->save();
            $type4->save();
            $type5->save();
        });
    }
};
