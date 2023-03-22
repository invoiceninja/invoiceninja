<?php

use App\Libraries\MultiDB;
use App\Models\CompanyUser;
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
        Schema::table('invoices', function (Blueprint $table) {
            $table->mediumText('tax_data')->nullable(); //json object
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('calculate_taxes')->default(false); //setting to turn on/off tax calculations
            $table->boolean('tax_all_products')->default(false); //globally tax all products if none defined
            $table->boolean('tax_data');
        });

        Schema::table('products', function (Blueprint $table){
            $table->unsignedInteger('tax_id')->nullable(); // the product tax constant
        });

        Schema::table('clients', function (Blueprint $table){
            $table->boolean('tax_data');
        });

        Schema::table('schedulers', function (Blueprint $table){
            $table->dropUnique('schedulers_company_id_name_unique');
        });

        Schema::table('schedulers', function (Blueprint $table) {
            $table->string('name', 191)->nullable()->change();
        });


        if (config('ninja.db.multi_db_enabled')) {
            foreach (MultiDB::$dbs as $db) {
                CompanyUser::on($db)->where('is_admin',0)->cursor()->each(function ($cu){

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
            }
        } else {
            
            
            CompanyUser::where('is_admin', 0)->cursor()->each(function ($cu) {
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
