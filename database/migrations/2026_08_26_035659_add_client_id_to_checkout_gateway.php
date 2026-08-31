<?php

use App\Models\CompanyGateway;
use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $gateway = Gateway::query()->where('key', '3758e7f7c6f4cecf0f4f348b9a00f456')->first();

        if ($gateway) {
            $fields = json_decode($gateway->fields, true);

            if (is_array($fields) && ! array_key_exists('clientId', $fields)) {
                $fields['clientId'] = '';
                $gateway->fields = json_encode($fields);
                $gateway->save();
            }
        }

        CompanyGateway::query()
            ->where('gateway_key', '3758e7f7c6f4cecf0f4f348b9a00f456')
            ->each(function (CompanyGateway $companyGateway): void {
                try {
                    $config = json_decode(decrypt($companyGateway->config), true);
                } catch (\Throwable) {
                    return;
                }

                if (! is_array($config) || array_key_exists('clientId', $config)) {
                    return;
                }

                $config['clientId'] = '';
                $companyGateway->config = encrypt(json_encode($config));
                $companyGateway->save();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
