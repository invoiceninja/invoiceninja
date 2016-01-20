<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInvoicesHasExpenses extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('invoices', function(Blueprint $table)
		{
			$table->boolean('has_expenses')->default(false);
		});

        $invoices = DB::table('invoices')
                    ->join('expenses', 'expenses.invoice_id', '=', 'invoices.id')
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
		Schema::table('invoices', function(Blueprint $table)
		{
			$table->dropColumn('has_expenses');
		});
	}

}
