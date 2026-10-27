<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($gateway = \App\Models\Gateway::where('key', 'hxd6gwg3ekb9tb3v9lptgx1mqyg69zu9')->first()) {
            $gateway->fields = json_encode(array_merge(json_decode($gateway->fields, true), ['webhookSecret' => '']));
            $gateway->save();

            \Illuminate\Support\Facades\Cache::forget('gateways');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
