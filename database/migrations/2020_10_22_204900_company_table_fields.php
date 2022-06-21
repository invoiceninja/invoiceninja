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
            $table->boolean('invoice_task_timelog')->default(true);
            $table->boolean('invoice_task_documents')->default(false);
            $table->dropColumn('use_credits_payment');
        });

        Schema::table('task_statuses', function (Blueprint $table) {
            $table->unsignedInteger('status_sort_order')->default(0);
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
