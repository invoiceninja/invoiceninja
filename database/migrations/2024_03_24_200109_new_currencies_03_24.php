<?php

use App\Models\Currency;
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

        $cur = Currency::find(122);

        if(!$cur) {
            $cur = new \App\Models\Currency();
            $cur->id = 122;
            $cur->code = 'BTN';
            $cur->name = "Bhutan Ngultrum";
            $cur->symbol = 'Nu';
            $cur->thousand_separator = ',';
            $cur->decimal_separator = '.';
            $cur->precision = 2;
            $cur->save();
        }

        $cur = Currency::find(123);

        if(!$cur) {
            $cur = new \App\Models\Currency();
            $cur->id = 123;
            $cur->code = 'MRU';
            $cur->name = "Mauritanian Ouguiya";
            $cur->symbol = 'UM';
            $cur->thousand_separator = ',';
            $cur->decimal_separator = '.';
            $cur->precision = 2;
            $cur->save();
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
