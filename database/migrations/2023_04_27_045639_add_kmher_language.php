<?php

use App\DataMapper\Tax\TaxModel;
use App\Models\Company;
use App\Models\Language;
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

        Language::unguard();

        $language = Language::find(38);

        if (! $language) {
            Language::create(['id' => 38, 'name' => 'Khmer', 'locale' => 'km_KH']);
        }

        if (Schema::hasColumn('companies', 'enable_e_invoice')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('enable_e_invoice');
            });
        }

        Company::query()->cursor()->each(function ($company) {
            $company->tax_data = new TaxModel();
            $company->save();
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
