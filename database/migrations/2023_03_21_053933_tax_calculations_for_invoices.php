<?php

use App\Models\Company;
use App\Models\CompanyUser;
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
        Schema::table('invoices', function (Blueprint $table) {
            $table->mediumText('tax_data')->nullable(); //json object
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('calculate_taxes')->default(false); //setting to turn on/off tax calculations
            $table->boolean('tax_all_products')->default(false); //globally tax all products if none defined
            $table->boolean('tax_data');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('tax_id')->nullable(); // the product tax constant
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('tax_data');
        });

        Schema::table('schedulers', function (Blueprint $table) {
            $table->dropUnique('schedulers_company_id_name_unique');
        });

        Schema::table('schedulers', function (Blueprint $table) {
            $table->string('name', 191)->nullable()->change();
        });
                    
        CompanyUser::query()->where('is_admin', 0)->cursor()->each(function ($cu) {
            $permissions = $cu->permissions;

            if (!$permissions || strlen($permissions) == 0) {
                $permissions = 'view_reports';
                $cu->permissions = $permissions;
                $cu->save();
            } else {
                $permissions_array = explode(',', $permissions);

                $permissions_array[] = 'view_reports';

                $modified_permissions_string = implode(",", $permissions_array);

                $cu->permissions = $modified_permissions_string;
                $cu->save();
            }
        });

        Company::query()
            ->cursor()
            ->each(function (Company $company) {
                $settings = $company->settings;

                if (!property_exists($settings, 'mailgun_endpoint')) {
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
        //
    }
};
