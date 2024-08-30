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
        Schema::table('products', function (Blueprint $table){
            $table->string('hash')->nullable();
        });

        Schema::table('companies', function (Blueprint $table){
            $table->bigInteger('legal_entity_id')->nullable();
        });


        if($currency = \App\Models\Currency::find(39))
        {
            $currency->symbol = 'лв';
            $currency->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
