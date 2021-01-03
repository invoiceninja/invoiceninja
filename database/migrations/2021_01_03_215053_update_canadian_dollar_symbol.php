<?php

use App\Models\Currency;
use App\Utils\Traits\AppSetup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateCanadianDollarSymbol extends Migration
{
    use AppSetup;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Currency::find(9)->update(['symbol' => '$']);

        $this->buildCache(true);
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
