<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeBraintreeProviderVisible extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $gateway = Gateway::where('key', 'f7ec488676d310683fb51802d076d713')->first();
        $gateway->visible = 1;
        $gateway->save();
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
