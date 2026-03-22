<?php

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
            $table->boolean('is_trial')->default(false);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('invoice_task_hours')->default(false);
        });

        Schema::table('schedulers', function (Blueprint $table) {
            $table->dropColumn('repeat_every');
            $table->dropColumn('start_from');
            $table->dropColumn('scheduled_run');
            $table->dropColumn('action_name');
            $table->dropColumn('action_class');
            $table->dropColumn('paused');
            $table->dropColumn('company_id');
        });


        Schema::table('schedulers', function (Blueprint $table) {
            $table->unsignedInteger('company_id');
            $table->boolean('is_paused')->default(false);
            $table->unsignedInteger('frequency_id')->nullable();
            $table->datetime('next_run')->nullable();
            $table->datetime('next_run_client')->nullable();
            $table->unsignedInteger('user_id');
            $table->string('name', 191);
            $table->string('template', 191);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade')->onUpdate('cascade');

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'deleted_at']);
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
