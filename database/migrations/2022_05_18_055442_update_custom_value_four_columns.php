<?php

use App\Utils\Ninja;
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
        set_time_limit(0);

        if (Ninja::isSelfHost()) {
            Schema::table('credits', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('client_contacts', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('clients', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('clients', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('documents', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('expenses', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('invoices', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('payments', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('products', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('projects', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('quotes', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('recurring_invoices', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('recurring_quotes', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('recurring_expenses', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('tasks', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('users', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('vendors', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });

            Schema::table('vendor_contacts', function (Blueprint $table) {
                $table->text('custom_value4')->change();
            });
        }
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
