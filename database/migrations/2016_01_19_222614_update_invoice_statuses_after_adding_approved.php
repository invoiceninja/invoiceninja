<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateInvoiceStatusesAfterAddingApproved extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        DB::table('invoices')
            ->whereIn('invoice_status_id', [4, 5])
            ->increment('invoice_status_id');
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        DB::table('invoices')
            ->whereIn('invoice_status_id', [5, 6])
            ->decrement('invoice_status_id');
	}

}
