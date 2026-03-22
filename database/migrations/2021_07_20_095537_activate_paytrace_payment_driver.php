<?php

use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ($paytrace = Gateway::find(46)) {
            $fields = json_decode($paytrace->fields);
            $fields->integratorId = '';

            $paytrace->fields = json_encode($fields);
            $paytrace->provider = 'Paytrace';
            $paytrace->visible = true;
            $paytrace->save();
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
