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
            $table->decimal('task_rate', 12, 4)->default(0);
        });

        Schema::table('clients', function ($table) {
            $table->decimal('task_rate', 12, 4)->default(0);
        });

        Schema::table('projects', function ($table) {
            $table->decimal('task_rate', 12, 4)->default(0);
        });

        Schema::table('invoices', function ($table) {
            $table->date('partial_due_date')->nullable();
        });

        Schema::table('users', function ($table) {
            $table->string('google_2fa_secret')->nullable();
        });

        // Add 'Four Months' frequency option
        if (DB::table('frequencies')->count() == 8) {
            DB::table('frequencies')->where('id', '=', 7)->update(['name' => 'Four months']);
            DB::table('frequencies')->where('id', '=', 8)->update(['name' => 'Six months']);
            DB::table('frequencies')->insert(['name' => 'Annually']);
            DB::statement('update invoices set frequency_id = frequency_id + 1 where frequency_id >= 7');
            DB::statement('update recurring_expenses set frequency_id = frequency_id + 1 where frequency_id >= 7');
            DB::statement('update accounts set reset_counter_frequency_id = reset_counter_frequency_id + 1 where reset_counter_frequency_id >= 7');
        }
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

        Schema::table('invoices', function ($table) {
            $table->dropColumn('partial_due_date');
        });

        Schema::table('users', function ($table) {
            $table->dropColumn('google_2fa_secret');
        });
    }
}
