<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRemember2faToken extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->string('remember_2fa_token', 100)->nullable();
        });

        Schema::dropIfExists('task_statuses');
        Schema::create('task_statuses', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->string('name')->nullable();
            $table->smallInteger('sort_order')->default(0);

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('tasks', function ($table) {
            $table->unsignedInteger('task_status_id')->index()->nullable();
            $table->smallInteger('task_status_sort_order')->default(0);
        });

        Schema::table('tasks', function ($table) {
            $table->foreign('task_status_id')->references('id')->on('task_statuses')->onDelete('cascade');
        });

        Schema::table('currencies', function ($table) {
            $table->decimal('exchange_rate', 13, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('remember_2fa_token');
        });

        Schema::table('tasks', function ($table) {
            $table->dropForeign('tasks_task_status_id_foreign');
        });

        Schema::table('tasks', function ($table) {
            $table->dropColumn('task_status_id');
            $table->dropColumn('task_status_sort_order');
        });

        Schema::dropIfExists('task_statuses');

        Schema::table('currencies', function ($table) {
            $table->dropColumn('exchange_rate');
        });
    }
}
