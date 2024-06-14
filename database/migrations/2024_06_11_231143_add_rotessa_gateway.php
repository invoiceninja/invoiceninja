<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Gateway;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Model::unguard();
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $record = Gateway::where('name', '=', 'Rotessa')->first();
        $count = (int) Gateway::count();

        $configuration = new \stdClass;
        $configuration->api_key = '';
        $configuration->test_mode =  true;

        if (!$record) {
            $gateway = new Gateway;
        } else {
            $gateway = $record;
        }

        $gateway->id = $count + 4000;
        $gateway->name = 'Rotessa'; 
        $gateway->key = Str::lower(Str::random(32)); 
        $gateway->provider = 'Rotessa';
        $gateway->is_offsite = true;
        $gateway->fields = \json_encode($configuration);
        $gateway->visible = 1;
        $gateway->site_url = "https://rotessa.com";
        $gateway->default_gateway_type_id = 2;
        $gateway->save();

 	Gateway::query()->where('name','=', 'Rotessa')->update(['visible' => 1]);

        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Gateway::where('name', '=', 'Rotessa')->delete();
    }
};
