<?php

use App\Models\Task;
use App\Models\TaskStatus;
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
        Schema::table('company_ledgers', function (Blueprint $table) {
            $table->decimal('balance', 20, 6)->change();
            $table->decimal('adjustment', 20, 6)->change();
        });

        Schema::table('credits', function (Blueprint $table) {
            $table->decimal('tax_rate1', 20, 6)->change();
            $table->decimal('tax_rate2', 20, 6)->change();
            $table->decimal('tax_rate3', 20, 6)->change();
            $table->decimal('total_taxes', 20, 6)->change();
            $table->decimal('exchange_rate', 20, 6)->change();
            $table->decimal('balance', 20, 6)->change();
            $table->decimal('partial', 20, 6)->change();
            $table->decimal('amount', 20, 6)->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('tax_rate1', 20, 6)->change();
            $table->decimal('tax_rate2', 20, 6)->change();
            $table->decimal('tax_rate3', 20, 6)->change();
            $table->decimal('total_taxes', 20, 6)->change();
            $table->decimal('exchange_rate', 20, 6)->change();
            $table->decimal('balance', 20, 6)->change();
            $table->decimal('partial', 20, 6)->change();
            $table->decimal('amount', 20, 6)->change();
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('tax_rate1', 20, 6)->change();
            $table->decimal('tax_rate2', 20, 6)->change();
            $table->decimal('tax_rate3', 20, 6)->change();
            $table->decimal('total_taxes', 20, 6)->change();
            $table->decimal('exchange_rate', 20, 6)->change();
            $table->decimal('balance', 20, 6)->change();
            $table->decimal('partial', 20, 6)->change();
            $table->decimal('amount', 20, 6)->change();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('tax_rate1', 20, 6)->change();
            $table->decimal('tax_rate2', 20, 6)->change();
            $table->decimal('tax_rate3', 20, 6)->change();
            $table->decimal('amount', 20, 6)->change();
            $table->decimal('foreign_amount', 20, 6)->change();
            $table->decimal('exchange_rate', 20, 6)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 20, 6)->change();
            $table->decimal('refunded', 20, 6)->change();
            $table->decimal('applied', 20, 6)->change();
            $table->decimal('exchange_rate', 20, 6)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('tax_rate1', 20, 6)->change();
            $table->decimal('tax_rate2', 20, 6)->change();
            $table->decimal('tax_rate3', 20, 6)->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('task_rate', 20, 6)->change();
            $table->decimal('budgeted_hours', 20, 6)->change();
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->decimal('tax_rate1', 20, 6)->change();
            $table->decimal('tax_rate2', 20, 6)->change();
            $table->decimal('tax_rate3', 20, 6)->change();
            $table->decimal('total_taxes', 20, 6)->change();
            $table->decimal('balance', 20, 6)->change();
            $table->decimal('amount', 20, 6)->change();
        });

        Schema::table('recurring_quotes', function (Blueprint $table) {
            $table->decimal('tax_rate1', 20, 6)->change();
            $table->decimal('tax_rate2', 20, 6)->change();
            $table->decimal('tax_rate3', 20, 6)->change();
            $table->decimal('total_taxes', 20, 6)->change();
            $table->decimal('balance', 20, 6)->change();
            $table->decimal('amount', 20, 6)->change();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('balance', 20, 6)->change();
            $table->decimal('paid_to_date', 20, 6)->change();
            $table->decimal('credit_balance', 20, 6)->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('rate', 20, 6)->change();
            $table->integer('status_sort_order')->nullable()->default(null)->change();
        });

        Schema::table('task_statuses', function (Blueprint $table) {
            $table->string('color')->default('#fff');
            $table->integer('status_sort_order')->nullable()->default(null)->change();
        });

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->decimal('rate', 20, 6)->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('calculate_expense_tax_by_amount')->false();
            $table->boolean('hide_empty_columns_on_pdf')->false();
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('color')->default('#fff');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('color')->default('#fff');
        });

        Task::query()->update(['status_sort_order' => null]);
        TaskStatus::query()->update(['status_sort_order' => null]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->integer('status_order')->nullable();
        });

        Schema::table('task_statuses', function (Blueprint $table) {
            $table->integer('status_order')->nullable();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('hide_empty_columns_on_pdf');
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
