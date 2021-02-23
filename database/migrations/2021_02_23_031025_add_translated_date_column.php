<?php

use App\Models\DateFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTranslatedDateColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('date_formats', function(Blueprint $table){
            $table->string('translated_format')->default('');
        });


        //add multi DB support
        $date_format = DateFormat::where('format', 'd/M/Y')->first();
        $date_format->update(['translated_format' => '%d/%b/%Y']);

        $date_format = DateFormat::where('format', 'd-M-Y')->first();
        $date_format->update(['translated_format' => '%d-%b-%Y']);

        $date_format = DateFormat::where('format', 'd/F/Y')->first();
        $date_format->update(['translated_format' => '%d/%B/%Y']);

        $date_format = DateFormat::where('format', 'd-F-Y')->first();
        $date_format->update(['translated_format' => '%d-%B-%Y']);

        $date_format = DateFormat::where('format', 'M j, Y')->first();
        $date_format->update(['translated_format' => '%b %d, %Y']);

        $date_format = DateFormat::where('format', 'F j, Y')->first();
        $date_format->update(['translated_format' => '%B %d, %Y']);

        $date_format = DateFormat::where('format', 'D M j, Y')->first();
        $date_format->update(['translated_format' => '%a %b %d, %Y']);

        $date_format = DateFormat::where('format', 'Y-m-d')->first();
        $date_format->update(['translated_format' => '%Y-%m-%d']);

        $date_format = DateFormat::where('format', 'd-m-Y')->first();
        $date_format->update(['translated_format' => '%d-%m-%Y']);

        $date_format = DateFormat::where('format', 'm/d/Y')->first();
        $date_format->update(['translated_format' => '%m/%d/%Y']);

        $date_format = DateFormat::where('format', 'd.m.Y')->first();
        $date_format->update(['translated_format' => '%d.%m.%Y']);

        $date_format = DateFormat::where('format', 'j. m. Y')->first();
        $date_format->update(['translated_format' => '%d. %m. %Y']);

        $date_format = DateFormat::where('format', 'j. F Y')->first();
        $date_format->update(['translated_format' => '%d. %B %Y']);

        $date_format = DateFormat::where('format', 'd/m/Y')->first();
        $date_format->update(['translated_format' => '%d/%m/%Y']);

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
}
