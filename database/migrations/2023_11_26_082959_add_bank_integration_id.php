<?php

use App\Models\BankIntegration;
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
        Schema::table('bank_integration', function (Blueprint $table) {
            $table->string('integration_type')->nullable();
        });

        // migrate old account to be used with yodlee
        BankIntegration::query()->whereNull('integration_type')->cursor()->each(function ($bank_integration) {
            $bank_integration->integration_type = BankIntegration::INTEGRATION_TYPE_YODLEE;
            $bank_integration->save();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bank_integration', function (Blueprint $table) {
            $table->dropColumn('integration_id');
        });
    }
};
