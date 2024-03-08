<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        $cur = Currency::find(119);

        if(!$cur) {
            $cur = new \App\Models\Currency();
            $cur->id = 119;
            $cur->code = 'MGA';
            $cur->name = 'Malagasy ariary';
            $cur->symbol = 'Ar';
            $cur->thousand_separator = ',';
            $cur->decimal_separator = '.';
            $cur->precision = 0;
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
