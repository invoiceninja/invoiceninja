<?php

use Illuminate\Database\Migrations\Migration;

class AddHasTasksToInvoices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoices', function ($table) {
            $table->boolean('has_tasks')->default(false);
        });

        $invoices = DB::table('invoices')
                    ->join('tasks', 'tasks.invoice_id', '=', 'invoices.id')
                    ->selectRaw('DISTINCT invoices.id')
                    ->get();

        foreach ($invoices as $invoice) {
            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update(['has_tasks' => true]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoices', function ($table) {
            $table->dropColumn('has_tasks');
        });
    }
}
