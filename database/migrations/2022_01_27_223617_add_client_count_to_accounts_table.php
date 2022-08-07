<?php

use App\Models\Country;
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
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedInteger('hosted_client_count')->nullable();
            $table->unsignedInteger('hosted_company_count')->nullable();
            $table->string('inapp_transaction_id', 100)->nullable();
        });

        $country = Country::find(250);

        if ($country) {
            $country->thousand_separator = ' ';
            $country->save();
        }
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
