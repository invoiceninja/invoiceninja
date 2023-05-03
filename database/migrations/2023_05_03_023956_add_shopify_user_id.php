<?php

use App\Models\Company;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Company::query()->cursor()->each(function ($company) {

            $settings = $company->settings;

            if(!property_exists($settings, 'enable_e_invoice')) {

                $company->saveSettings((array)$company->settings, $company);

            }

        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('shopify_user_id')->index()->nullable();
        });



        //902541635
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
