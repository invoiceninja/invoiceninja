<?php

use App\Models\Currency;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Eloquent\Model;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if(!Currency::find(143)){

            
            Model::unguard();

            $currency =  ['id' => 143, 'name' => 'Caribbean guilder', 'code' => 'XCG', 'symbol' => 'Cg', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','];

            Currency::create($currency);

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
