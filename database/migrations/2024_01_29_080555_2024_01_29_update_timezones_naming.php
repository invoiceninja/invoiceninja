<?php

use App\Models\Timezone;
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
        if($t = Timezone::find(2)){
            $t->name = 'Pacific/Apia';
            $t->save();
        }

        if($t = Timezone::find(3)) {
            $t->name = 'Pacific/Honolulu';
            $t->save();
        }

        if($t = Timezone::find(4)) {
            $t->name = 'America/Anchorage';
            $t->save();
        }

        if($t = Timezone::find(5)) {
            $t->name = 'America/Los_Angeles';
            $t->save();
        }

        if($t = Timezone::find(7)) {
            $t->name = 'America/Phoenix';
            $t->save();
        }
        
        if($t = Timezone::find(8)) {
            $t->name = 'America/Denver';
            $t->save();
        }

        if($t = Timezone::find(13)) {
            $t->name = 'America/Regina';
            $t->save();
        }

        if($t = Timezone::find(14)) {
            $t->name = 'America/Chicago';
            $t->save();
        }

        if($t = Timezone::find(15)) {
            $t->name = 'America/New_York';
            $t->save();
        }

        if($t = Timezone::find(16)) {
            $t->name = 'America/Indiana/Indianapolis';
            $t->save();
        }

        if($t = Timezone::find(20)) {
            $t->name = 'America/Halifax';
            $t->save();
        }

        if($t = Timezone::find(23)) {
            $t->name = 'America/St_Johns';
            $t->save();
        }

        if($t = Timezone::find(24)) {
            $t->name = 'America/Argentina/Buenos_Aires';
            $t->save();
        }

        if($t = Timezone::find(25)) {
            $t->name = 'America/Nuuk';
            $t->save();
        }
        
        if($t = Timezone::find(59)) {
            $t->name = 'Europe/Kyiv';
            $t->save();
        }

        if($t = Timezone::find(90)) {
            $t->name = 'Asia/Shanghai';
            $t->save();
        }

        if($t = Timezone::find(105)) {
            $t->name = 'Australia/Sydney';
            $t->save();
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
