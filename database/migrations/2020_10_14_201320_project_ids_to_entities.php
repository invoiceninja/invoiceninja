<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ProjectIdsToEntities extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedInteger('project_id')->nullable();
        });

        Schema::table('gateways', function (Blueprint $table) {
            $table->longText('fields')->change();
        });

        Schema::table('gateways', function (Blueprint $table) {
            $table->boolean('mark_expenses_invoiceable')->default(0);
            $table->boolean('mark_expenses_paid')->default(0);
            $table->enum('use_credits_payment', ['always', 'off', 'optin'])->nullable();
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
}
