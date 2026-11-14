<?php

use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $gt = GatewayType::find(GatewayType::CARD_PRESENT);

        if (!$gt) {
            $type = new GatewayType();
            $type->id = GatewayType::CARD_PRESENT;
            $type->alias = 'card_present';
            $type->name = 'Card Present';
            $type->save();
        }
    }
};
