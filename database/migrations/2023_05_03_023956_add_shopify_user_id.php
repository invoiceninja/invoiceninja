<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

        Schema::table('users', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedBigInteger('shopify_user_id')->index()->nullable();
        });

        Schema::table('companies', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->string('shopify_name')->index()->nullable();
            $table->string('shopify_access_token')->index()->nullable();
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
};
