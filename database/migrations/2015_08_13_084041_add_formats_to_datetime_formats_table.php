<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddFormatsToDatetimeFormatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('date_formats')
            ->where('label', '20/03/2013')
            ->update(['label' => '20-03-2013']);

        DB::table('datetime_formats')
            ->where('label', '20/03/2013 6:15 pm')
            ->update(['label' => '20-03-2013 6:15 pm']);

        Schema::table('datetime_formats', function (Blueprint $table) {
            $table->string('format_moment');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('datetime_formats', function (Blueprint $table) {
            $table->dropColumn('format_moment');
        });
    }
}
