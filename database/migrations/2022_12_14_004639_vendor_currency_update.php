<?php

use App\Models\Vendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        Schema::table('companies', function (Blueprint $table)
        {
            $table->dropColumn('use_vendor_currency');
        });

        Vendor::query()->whereNull('currency_id')->orWhere('currency_id', '')->cursor()->each(function ($vendor){

            $vendor->currency_id = $vendor->company->settings->currency_id;
            $vendor->save();

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
