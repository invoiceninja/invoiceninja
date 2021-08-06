<?php

use App\Models\PaymentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZellePaymentType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $pt = PaymentType::where('name', 'Zelle')->first();

        if(!$pt){

            $payment_type = new PaymentType();
            $payment_type->name = 'Zelle';
            $payment_type->save();
        }
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
