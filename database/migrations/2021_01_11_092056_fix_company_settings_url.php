<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixCompanySettingsUrl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Company::all()->each(function ($company){

            $settings = $company->settings;

            $company_logo = $settings->company_logo;
            $company_logo = str_replace(config('ninja.app_url'), '', $company_logo);

            $settings->company_logo = $company_logo;

            $company->settings = $settings;
            $company->save();

        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('logo');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('start_time');
        });
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
