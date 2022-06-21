<?php

use App\Models\Credit;
use App\Models\Gateway;
use App\Models\Invoice;
use App\Models\Quote;
use App\Utils\Ninja;
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
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //Fixes a state where the deleted_at timestamp is 000

        if (! Ninja::isHosted()) {
            Invoice::withTrashed()->where('deleted_at', '0000-00-00 00:00:00.000000')->update(['deleted_at' => null]);
            Quote::withTrashed()->where('deleted_at', '0000-00-00 00:00:00.000000')->update(['deleted_at' => null]);
            Credit::withTrashed()->where('deleted_at', '0000-00-00 00:00:00.000000')->update(['deleted_at' => null]);
        }

        // fixes a bool cast to string back to bool
        if ($gateway = Gateway::find(57)) {
            $fields = json_decode($gateway->fields);
            $fields->testMode = false;

            $gateway->fields = json_encode($fields);
            $gateway->save();
        }
    }
};
