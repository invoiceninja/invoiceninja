<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('user_logged_in_notification')->default(true);
        });


        $cur = Currency::find(120);

        if(!$cur) {
            $cur = new \App\Models\Currency();
            $cur->id = 120;
            $cur->code = 'TOP';
            $cur->name = "Tongan Pa'anga";
            $cur->symbol = 'T$';
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
