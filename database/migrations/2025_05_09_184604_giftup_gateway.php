<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $gateway = new Gateway;
    	$gateway->name = "GiftUp"; 
    	$gateway->key = Str::lower(Str::random(32)); 
    	$gateway->provider = "GiftUp";
    	$gateway->is_offsite = false; 
    	
    	
        $configuration = new \stdClass;
        $configuration->apiKey = '';
        $configuration->testMode =  true;
         
        $gateway->fields = \json_encode($configuration);
        
    	$gateway->visible = true;
    	$gateway->site_url = "https://www.giftup.com/";
    	$gateway->default_gateway_type_id = 30;
    	$gateway->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
