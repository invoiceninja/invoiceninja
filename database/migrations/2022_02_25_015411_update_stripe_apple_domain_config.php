<?php

use App\Models\CompanyGateway;
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
        CompanyGateway::whereIn('gateway_key', ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'])->cursor()->each(function ($cg) {
            $config = $cg->getConfig();

            if (! property_exists($config, 'appleDomainVerification')) {
                $config->appleDomainVerification = '';
                $cg->setConfig($config);
                $cg->save();
            }
        });
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
