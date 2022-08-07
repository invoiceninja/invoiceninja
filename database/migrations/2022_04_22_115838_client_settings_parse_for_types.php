<?php

use App\Models\Client;
use App\Utils\Ninja;
use App\Utils\Traits\ClientGroupSettingsSaver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use ClientGroupSettingsSaver;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Ninja::isSelfHost()) {
            Client::withTrashed()->cursor()->each(function ($client) {
                $entity_settings = $this->checkSettingType($client->settings);
                $entity_settings->md5 = md5(time());
                $client->settings = $entity_settings;
                $client->save();
            });
        }
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
