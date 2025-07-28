<?php

use App\Models\DateFormat;
use App\Models\DatetimeFormat;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if($date_format = DateFormat::where('format', 'd.m.Y')->first()){
            $date_format->format_moment = 'DD.MM.YYYY';
            $date_format->save();
        }

                
        if ($date_format = DatetimeFormat::find(11)) {
            $date_format->format_moment = 'DD.MM.YYYY h:mm:ss a';
            $date_format->save();
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
