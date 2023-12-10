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
        if ($eway = Gateway::find(3)) {
            $eway->visible = true;
            $eway->provider = 'Eway';

            $fields = json_decode($eway->fields);
            $fields->publicApiKey = '';
            $eway->fields = json_encode($fields);

            $eway->save();
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
