<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $ir = \App\Models\Currency::query()->where('code', 'IDR')->first();

        if($ir) {
            $ir->thousand_separator = '.';
            $ir->decimal_separator = ',';
            $ir->save();
        }

        $ld = \App\Models\Currency::find(115);

        if(!$ld) {
            $ld = new \App\Models\Currency();
            $ld->id = 115;
            $ld->code = 'LYD';
            $ld->name = 'Libyan Dinar';
            $ld->symbol = 'LD';
            $ld->thousand_separator = ',';
            $ld->decimal_separator = '.';
            $ld->precision = 3;
            $ld->save();
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
