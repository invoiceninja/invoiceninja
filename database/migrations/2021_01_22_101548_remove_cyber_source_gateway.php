<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveCyberSourceGateway extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        /* No longer supported for V2 Omnipay */
        
        if(Gatway::count() > 0)
        {
            $cyber = Gateway::where('provider', 'Cybersource')->first();
            $cyber->payment_library_id = 2;
            $cyber->save();
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
