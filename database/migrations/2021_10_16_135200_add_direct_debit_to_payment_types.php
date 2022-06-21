<?php

use App\Models\GatewayType;
use App\Models\PaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('payment_types', function (Blueprint $table) {
            $type = new PaymentType();

            $type->id = 42;
            $type->name = 'Direct Debit';
            $type->gateway_type_id = GatewayType::DIRECT_DEBIT;

            $type->save();
        });
    }
};
