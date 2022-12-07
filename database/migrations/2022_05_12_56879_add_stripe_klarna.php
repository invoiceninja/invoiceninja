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

            $type->id = 47;
            $type->name = 'Klarna';
            $type->gateway_type_id = GatewayType::KLARNA;

            $type->save();
        });
        $type = new GatewayType();

        $type->id = 23;
        $type->alias = 'klarna';
        $type->name = 'Klarna';

        $type->save();
    }
};
