<?php

use App\Models\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $currencies = [

            ['id' => 112, 'name' => 'Cayman Island Dollar', 'code' => 'KYD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],

        ];

        foreach ($currencies as $currency) {
            $record = Currency::whereCode($currency['code'])->first();
            if ($record) {
                $record->name = $currency['name'];
                $record->symbol = $currency['symbol'];
                $record->precision = $currency['precision'];
                $record->thousand_separator = $currency['thousand_separator'];
                $record->decimal_separator = $currency['decimal_separator'];
                if (isset($currency['swap_currency_symbol'])) {
                    $record->swap_currency_symbol = $currency['swap_currency_symbol'];
                }
                $record->save();
            } else {
                Currency::create($currency);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
