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
        if ($df = App\Models\DateFormat::find(7)) {
            $df->format_moment = 'ddd MMM D, YYYY';
            $df->save();
        }

        if ($df = App\Models\DateFormat::find(14)) {
            $df->format_moment = 'DD/MM/YYYY';
            $df->save();
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
