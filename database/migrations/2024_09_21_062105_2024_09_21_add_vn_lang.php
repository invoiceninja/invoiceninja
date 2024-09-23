<?php

use App\Models\Currency;
use App\Models\Language;
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
        
        Language::unguard();

        $language = Language::find(42);

        if (! $language) {
            Language::create(['id' => 42, 'name' => 'Vietnamese', 'locale' => 'vi']);
        }

        if($currency = Currency::find(16)) {
            $currency->symbol = '₫';
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
