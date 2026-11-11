<?php

use App\Models\CompanyGateway;
use App\Utils\Ninja;
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
        Schema::table('company_gateways', function (Blueprint $table) {
            $table->string('gateway_account_id')->nullable()->index();
        });

        if(Ninja::isHosted()){

            try{
                CompanyGateway::withTrashed()
                                ->whereIn('gateway_key', [
                                    'd14dd26a37cecc30fdd65700bfb55b23',
                                    'd14dd26a47cecc30fdd65700bfb67b34',
                                ])
                                ->whereNull('gateway_account_id')
                                ->cursor()
                                ->each(function ($company_gateway) {

                                    $config = $company_gateway->getConfig();

                                    if(isset($config->account_id)){
                                        $company_gateway->gateway_account_id = $config->account_id;
                                        $company_gateway->saveQuietly();
                                    }

                                });

            } catch (\Throwable $e) {
                //
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
