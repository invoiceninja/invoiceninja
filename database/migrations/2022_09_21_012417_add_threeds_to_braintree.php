<?php

use App\Models\CompanyGateway;
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
        $g = Gateway::find(50);

        if ($g) {
            $fields = json_decode($g->fields);
            $fields->threeds = false;

            $g->fields = json_encode($fields);
            $g->save();
        }

        CompanyGateway::query()->where('gateway_key', 'f7ec488676d310683fb51802d076d713')->cursor()->each(function ($cg) {
            $config = $cg->getConfig();
            $config->threeds = false;
            $cg->setConfig($config);

            $cg->save();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
