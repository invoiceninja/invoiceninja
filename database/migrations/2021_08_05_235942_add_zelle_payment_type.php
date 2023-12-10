<?php

use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Model::unguard();

        $pt = PaymentType::query()->where('name', 'Zelle')->first();

        if (! $pt) {
            $payment_type = new PaymentType();
            $payment_type->id = 33;
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
};
