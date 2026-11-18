<?php

use App\Libraries\MultiDB;
use App\Models\Gateway;
use App\Models\GatewayType;
use App\Models\PaymentType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        if (! PaymentType::find(PaymentType::REVOLUT_PAY)) {
            $type = new PaymentType();
            $type->id = PaymentType::REVOLUT_PAY;
            $type->name = 'Revolut Pay';
            $type->gateway_type_id = GatewayType::REVOLUT_PAY;
            $type->save();
        }

        if (! GatewayType::find(GatewayType::REVOLUT_PAY)) {
            $type = new GatewayType();
            $type->id = GatewayType::REVOLUT_PAY;
            $type->alias = 'revolut_pay';
            $type->name = 'Revolut Pay';
            $type->save();
        }

        // Revolut Pay needs the merchant's public API key in the browser.
        $connections = config('ninja.db.multi_db_enabled')
            ? MultiDB::getDbs()
            : [config('database.default')];

        foreach ($connections as $connection) {
            Gateway::on($connection)
                ->where('key', '764562d0ba104d73081499b2a40df091')
                ->cursor()
                ->each(function ($gateway) {
                    $fields = $gateway->fields;

                    if (is_string($fields)) {
                        $fields = json_decode($fields, true, 512, JSON_THROW_ON_ERROR);
                    }

                    $fields = (array) $fields;

                    if (! array_key_exists('publicKey', $fields)) {
                        $fields = ['apiKey' => $fields['apiKey'] ?? '', 'publicKey' => ''] + $fields;
                        $gateway->fields = json_encode($fields, JSON_THROW_ON_ERROR);
                        $gateway->save();
                    }
                });
        }

        \Illuminate\Support\Facades\Cache::forget('gateways');
        app()->forgetInstance('gateways');
    }

    public function down(): void
    {
    }
};
