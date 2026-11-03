<?php

use App\Libraries\MultiDB;
use App\Models\Gateway;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connections = config('ninja.db.multi_db_enabled')
            ? MultiDB::getDbs()
            : [config('database.default')];

        foreach ($connections as $connection) {
            Gateway::on($connection)
                ->where('key', 'hxd6gwg3ekb9tb3v9lptgx1mqyg69zu9')
                ->cursor()
                ->each(function ($gateway) {
                    $gateway_fields = $gateway->fields;

                    // Gateway metadata is exposed as JSON text by GatewayTransformer.
                    if (is_string($gateway_fields)) {
                        $gateway_fields = json_decode($gateway_fields, true, 512, JSON_THROW_ON_ERROR);
                    }

                    $gateway_fields = (array) $gateway_fields;

                    if (! array_key_exists('webhookSecret', $gateway_fields)) {
                        $gateway_fields['webhookSecret'] = '';
                    }

                    $gateway->fields = json_encode($gateway_fields, JSON_THROW_ON_ERROR);

                    if ($gateway->isDirty('fields')) {
                        $gateway->save();
                    }
                });
        }

        \Illuminate\Support\Facades\Cache::forget('gateways');
        app()->forgetInstance('gateways');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
