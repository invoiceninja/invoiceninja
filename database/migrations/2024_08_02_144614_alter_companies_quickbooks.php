<?php

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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('quickbooks_realm_id')->nullable();
            $table->string('quickbooks_refresh_token')->nullable();
            $table->dateTime('quickbooks_refresh_expires')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['quickbooks_realm_id', 'quickbooks_refresh_token','quickbooks_refresh_expires']);
        });
    }
};
