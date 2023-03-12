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
        if ($gateway = Gateway::find(50)) {
            $fields = json_decode($gateway->fields);
            $fields->merchantAccountId = '';
            $gateway->fields = json_encode($fields);

            $gateway->save();
        }
    }
};
