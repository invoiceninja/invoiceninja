<?php

use App\Libraries\MultiDB;
use App\Models\Gateway;
use App\Models\GatewayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {

    public function up(): void
    {
        $connections = config('ninja.db.multi_db_enabled')
            ? MultiDB::getDbs()
            : [config('database.default')];

        Model::unguard();

        foreach ($connections as $connection) {
            if (Gateway::on($connection)->find(71)) {
                continue;
            }

            $gateway = new Gateway();
            $gateway->setConnection($connection);
            $gateway->id = 71;
            $gateway->name = 'Revolut';
            $gateway->key = '764562d0ba104d73081499b2a40df091';
            $gateway->provider = 'Revolut';
            $gateway->is_offsite = true;
            $gateway->fields = json_encode(['apiKey' => '', 'signingSecret' => '', 'testMode' => false]);
            $gateway->visible = true;
            $gateway->sort_order = 33;
            $gateway->site_url = 'https://developer.revolut.com/docs/guides/merchant/get-started';
            $gateway->default_gateway_type_id = GatewayType::HOSTED_PAGE;
            $gateway->save();
        }

        Model::reguard();
    }

    public function down(): void
    {
        //
    }
};
