<?php

use App\Models\CompanyGateway;
use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if($gateway = Gateway::find(39)) {
            $fields = json_decode($gateway->fields);
            $fields->webhookSecret = '';
            $gateway->fields = json_encode($fields);
            $gateway->save();
        }

        CompanyGateway::withTrashed()
            ->where('gateway_key', '3758e7f7c6f4cecf0f4f348b9a00f456')
            ->lazy(100)
            ->each(function ($company_gateway) {
                $config = $company_gateway->getConfig();
                $config->webhookSecret = $company_gateway->company->company_key;
                $company_gateway->setConfig($config);
                $company_gateway->save();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
