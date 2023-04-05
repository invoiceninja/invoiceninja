<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
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
        Schema::table('clients', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->boolean('is_tax_exempt')->default(false);
            $table->boolean('has_valid_vat_number')->default(false);
            $table->mediumText('tax_data')->nullable()->change();
        });

        Schema::table('companies', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->mediumText('tax_data')->nullable()->change();
            $table->dropColumn('tax_all_products');
        });

        Schema::table('projects', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->unsignedInteger('current_hours')->nullable();
        });


        Company::query()
               ->cursor()
               ->each(function ($company) {
                   $company->tax_data = null;
                   $company->save();
               });
        
        Client::query()
               ->cursor()
               ->each(function ($client) {
                   $client->tax_data = null;
                   $client->save();
               });

        Product::query()
               ->cursor()
               ->each(function ($product) {
                   $product->tax_id = 1;
                   $product->save();
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
