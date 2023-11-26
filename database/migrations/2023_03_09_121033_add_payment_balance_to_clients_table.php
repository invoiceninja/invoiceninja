<?php

use App\Models\Company;
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

        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('payment_balance', 20, 6)->default(0);
        });
    
        Company::query()
            ->cursor()
            ->each(function (Company $company) {

                $settings = $company->settings;

                if(!property_exists($settings, 'show_task_item_description')) {
                    $company->saveSettings((array)$company->settings, $company);
                }


            });
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
