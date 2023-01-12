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
        
        Schema::table('accounts', function (Blueprint $table)
        {
            $table->boolean('is_trial')->default(false);
        });

        Schema::table('companies', function (Blueprint $table)
        {
            $table->boolean('invoice_task_hours')->default(false);
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
