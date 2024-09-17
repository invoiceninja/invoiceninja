<?php

use App\Models\Gateway;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
                
        Model::unguard();

        $fields = new \stdClass();

        $fields->publicKey = '';
        $fields->secretKey = '';
        $fields->testMode = false;
        $fields->threeds = false;

        $powerboard = new Gateway();
        $powerboard->id = 64;
        $powerboard->name = 'CBA PowerBoard';
        $powerboard->provider = 'CBAPowerBoard';
        $powerboard->key = 'b67581d804dbad1743b61c57285142ad';
        $powerboard->sort_order = 4543;
        $powerboard->is_offsite = false;
        $powerboard->visible = true;
        $powerboard->fields = json_encode($fields);
        $powerboard->save();


        Schema::table("company_gateways", function (\Illuminate\Database\Schema\Blueprint $table){
            $table->text('settings')->nullable();
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
