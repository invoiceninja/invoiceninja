<?php

use Illuminate\Database\Migrations\Migration;

class AddInvoiceTypeSupport extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('invoices', 'is_quote')) {
            DB::update('update invoices set is_quote = is_quote + 1');

            Schema::table('invoices', function ($table) {
                $table->renameColumn('is_quote', 'invoice_type_id');
            });
        }

        Schema::table('accounts', function ($table) {
            $table->boolean('enable_second_tax_rate')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('invoices', 'invoice_type_id')) {
            DB::update('update invoices set invoice_type_id = invoice_type_id - 1');
        }

        Schema::table('accounts', function ($table) {
            $table->dropColumn('enable_second_tax_rate');
        });
    }
}
