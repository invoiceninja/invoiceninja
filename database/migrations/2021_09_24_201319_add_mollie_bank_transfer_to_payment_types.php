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
        $type = new PaymentType();

        $type->id = 34;
        $type->name = 'Mollie Bank Transfer';
        $type->gateway_type_id = GatewayType::BANK_TRANSFER;

        $type->save();
    }
};
