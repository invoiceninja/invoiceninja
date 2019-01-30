<?php

use Illuminate\Database\Migrations\Migration;

class AddSupportForInvoiceDesigns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice_designs', function ($table) {
            $table->increments('id');
            $table->string('name');
        });

        DB::table('invoice_designs')->insert(['name' => 'Clean']);
        DB::table('invoice_designs')->insert(['name' => 'Bold']);
        DB::table('invoice_designs')->insert(['name' => 'Modern']);
        DB::table('invoice_designs')->insert(['name' => 'Plain']);

        Schema::table('invoices', function ($table) {
            $table->unsignedInteger('invoice_design_id')->default(1);
        });

        Schema::table('accounts', function ($table) {
            $table->unsignedInteger('invoice_design_id')->default(1);
        });

        DB::table('invoices')->update(['invoice_design_id' => 1]);
        DB::table('accounts')->update(['invoice_design_id' => 1]);

        Schema::table('invoices', function ($table) {
            $table->foreign('invoice_design_id')->references('id')->on('invoice_designs');
        });
    
        Schema::table('accounts', function ($table) {
            $table->foreign('invoice_design_id')->references('id')->on('invoice_designs');
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
            $table->dropForeign('invoices_invoice_design_id_foreign');
            $table->dropColumn('invoice_design_id');
        });

        Schema::table('accounts', function ($table) {
            $table->dropForeign('accounts_invoice_design_id_foreign');
            $table->dropColumn('invoice_design_id');
        });

        Schema::dropIfExists('invoice_designs');
    }
}
