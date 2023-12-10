<?php

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
        Schema::table('purchase_order_invitations', function (Blueprint $table) {
            $table->text('signature_ip')->nullable();
        });

        $xag = \App\Models\Currency::find(116);

        if(!$xag) {

            $xag = new \App\Models\Currency();
            $xag->id = 116;
            $xag->code = 'XAG';
            $xag->name = 'Silver Troy Ounce';
            $xag->symbol = 'XAG';
            $xag->thousand_separator = ',';
            $xag->decimal_separator = '.';
            $xag->precision = 2;
            $xag->save();
        }

        $xau = \App\Models\Currency::find(117);

        if(!$xau) {

            $xau = new \App\Models\Currency();
            $xau->id = 117;
            $xau->code = 'XAU';
            $xau->name = 'Gold Troy Ounce';
            $xau->symbol = 'XAU';
            $xau->thousand_separator = ',';
            $xau->decimal_separator = '.';
            $xau->precision = 3;
            $xau->save();
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
