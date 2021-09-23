<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCurrencies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    
    $currencies = [
        ['id' => 105, 'name' => 'Ethiopian Birr', 'code' => 'ETB', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],

    ];

    }
}


// Gambia Dalasi (GMD)
// Paraguayan Guarani (PYG)
// Malawi Kwacha (MWK)
// Zimbabwean Dollar (ZWL)
// Cambodian Riel (KHR)
// Vanuatu Vatu (VUV)