<?php

use App\Models\Currency;
use App\Utils\Traits\AppSetup;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    use AppSetup;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $currency = Currency::find(9);

        if ($currency) {
            $currency->update(['symbol' => '$']);
        }

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
};
