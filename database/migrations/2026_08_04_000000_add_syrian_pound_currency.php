<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Currency::where('code', 'SYP')->exists()) {
            return;
        }

        $currency = new Currency();
        $currency->id = 144;
        $currency->name = 'Syrian Pound';
        $currency->code = 'SYP';
        $currency->symbol = 'ل.س';
        $currency->precision = 2;
        $currency->thousand_separator = ',';
        $currency->decimal_separator = '.';
        $currency->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Currency IDs may be referenced by existing records, so this is intentionally irreversible.
    }
};
