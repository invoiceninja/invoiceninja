<?php

use App\DataMapper\ClientRegistrationFields;
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
        Company::query()->cursor()->each(function ($company){

            $crfs = $company->client_registration_fields;
            
            if(!$crfs) {
                $crfs = ClientRegistrationFields::generate();           
            }

            foreach($crfs as $key => $crf)
            {
                $crfs[$key]['visible'] = $crfs[$key]['required'];
            }

            $company->client_registration_fields = $crfs;
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
        
    }
};
