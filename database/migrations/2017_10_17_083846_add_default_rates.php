<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDefaultRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('accounts', function ($table) {
            $table->decimal('task_rate', 12, 4);
        });

        Schema::table('clients', function ($table) {
            $table->decimal('task_rate', 12, 4);
        });

        Schema::table('projects', function ($table) {
            $table->decimal('task_rate', 12, 4);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('accounts', function ($table) {
            $table->dropColumn('task_rate');
        });

        Schema::table('clients', function ($table) {
            $table->dropColumn('task_rate');
        });

        Schema::table('projects', function ($table) {
            $table->dropColumn('task_rate');
        });
    }
}
