<?php

use App\Models\Gateway;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use AppSetup;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Ninja::isSelfHost()) {
            Model::unguard();

            $stripe = [
                'name' => 'Stripe',
                'provider' => 'Stripe',
                'sort_order' => 1,
                'key' => 'd14dd26a37cecc30fdd65700bfb55b23',
                'fields' => '{"publishableKey":"","apiKey":"","appleDomainVerification":""}',
            ];

            $record = Gateway::find(20);

            if ($record) {
                $record->fill($stripe);
                $record->save();
            }

            $this->buildCache(true);
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
