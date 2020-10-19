<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ProjectNameUniqueRemoval extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique('projects_company_id_name_unique');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedInteger('invoice_currency_id')->nullable()->change();
            $table->unsignedInteger('expense_currency_id')->nullable()->change();
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
}
