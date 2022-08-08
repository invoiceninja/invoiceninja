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
    public function up(): void
    {
        $type = new PaymentType();

        $type->id = PaymentType::INSTANT_BANK_PAY;
        $type->name = 'Instant Bank Pay';
        $type->gateway_type_id = GatewayType::INSTANT_BANK_PAY;

        $type->save();
    }
};
