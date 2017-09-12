<?php

use Illuminate\Database\Migrations\Migration;

class AddTaskProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('client_id')->index()->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->string('name')->nullable();
            $table->boolean('is_deleted')->default(false);

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['account_id', 'public_id']);
        });

        Schema::table('tasks', function ($table) {
            $table->unsignedInteger('project_id')->nullable()->index();
            if (Schema::hasColumn('tasks', 'description')) {
                $table->text('description')->change();
            }
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::table('tasks', function ($table) {
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // is_deleted to standardize tables
        Schema::table('expense_categories', function ($table) {
            $table->boolean('is_deleted')->default(false);
        });

        Schema::table('products', function ($table) {
            $table->boolean('is_deleted')->default(false);
        });

        // add 'delete cascase' to resolve error when deleting an account
        Schema::table('account_gateway_tokens', function ($table) {
            $table->dropForeign('account_gateway_tokens_default_payment_method_id_foreign');
        });

        Schema::table('account_gateway_tokens', function ($table) {
            $table->foreign('default_payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
        });

        Schema::table('invoices', function ($table) {
            $table->boolean('is_public')->default(false);
        });
        DB::table('invoices')->update(['is_public' => true]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tasks', function ($table) {
            $table->dropForeign('tasks_project_id_foreign');
            $table->dropColumn('project_id');
        });

        Schema::dropIfExists('projects');

        Schema::table('expense_categories', function ($table) {
            $table->dropColumn('is_deleted');
        });

        Schema::table('products', function ($table) {
            $table->dropColumn('is_deleted');
        });

        Schema::table('invoices', function ($table) {
            $table->dropColumn('is_public');
        });
    }
}
