<?php

use Illuminate\Database\Migrations\Migration;

class SupportMultipleTaxRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function ($table) {
            if (Schema::hasColumn('invoices', 'tax_rate')) {
                $table->decimal('tax_rate', 13, 3)->change();
            }
        });

        Schema::table('invoice_items', function ($table) {
            if (Schema::hasColumn('invoice_items', 'tax_rate')) {
                $table->decimal('tax_rate', 13, 3)->change();
            }
        });

        Schema::table('invoices', function ($table) {
            if (Schema::hasColumn('invoices', 'tax_rate')) {
                $table->renameColumn('tax_rate', 'tax_rate1');
                $table->renameColumn('tax_name', 'tax_name1');
            }
            $table->string('tax_name2')->nullable();
            $table->decimal('tax_rate2', 13, 3);
        });

        Schema::table('invoice_items', function ($table) {
            if (Schema::hasColumn('invoice_items', 'tax_rate')) {
                $table->renameColumn('tax_rate', 'tax_rate1');
                $table->renameColumn('tax_name', 'tax_name1');
            }
            $table->string('tax_name2')->nullable();
            $table->decimal('tax_rate2', 13, 3);
        });

        Schema::table('accounts', function ($table) {
            $table->boolean('enable_client_portal_dashboard')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function ($table) {
            $table->decimal('tax_rate1', 13, 2)->change();
            $table->renameColumn('tax_rate1', 'tax_rate');
            $table->renameColumn('tax_name1', 'tax_name');
            $table->dropColumn('tax_name2');
            $table->dropColumn('tax_rate2');
        });

        Schema::table('invoice_items', function ($table) {
            $table->decimal('tax_rate1', 13, 2)->change();
            $table->renameColumn('tax_rate1', 'tax_rate');
            $table->renameColumn('tax_name1', 'tax_name');
            $table->dropColumn('tax_name2');
            $table->dropColumn('tax_rate2');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('enable_client_portal_dashboard');
        });
    }
}
