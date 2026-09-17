<?php

use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        CompanyGateway::query()
            ->withTrashed()
            ->where('gateway_key', 'b9886f9257f0c6ee7c302f1c74475f6c')
            ->eachById(function (CompanyGateway $company_gateway): void {
                ClientGatewayToken::query()
                    ->withTrashed()
                    ->where('company_gateway_id', $company_gateway->id)
                    ->where('gateway_type_id', GatewayType::DIRECT_DEBIT)
                    ->eachById(function (ClientGatewayToken $token): void {
                        if ((int) data_get($token->meta, 'type') !== GatewayType::SEPA) {
                            return;
                        }

                        $meta = $token->meta ?? new \stdClass();
                        $meta->scheme = 'sepa_core';
                        $token->gateway_type_id = GatewayType::SEPA;
                        $token->meta = $meta;
                        $token->saveQuietly();
                    });
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
