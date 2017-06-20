<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateDarkMode extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->boolean('dark_mode')->default(true)->change();
        });

        DB::statement('update users set dark_mode = 1;');

        // update invoice_item_type_id for task invoice items
        DB::statement('update invoice_items
            left join invoices on invoices.id = invoice_items.invoice_id
            set invoice_item_type_id = 2
            where invoices.has_tasks = 1
            and invoice_item_type_id = 1');        
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
}
