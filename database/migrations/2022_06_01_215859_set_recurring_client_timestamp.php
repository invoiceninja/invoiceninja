<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        set_time_limit(0);

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->datetime('next_send_date_client')->nullable();
        });

        Schema::table('recurring_expenses', function (Blueprint $table) {
            $table->datetime('next_send_date_client')->nullable();
        });

        RecurringInvoice::withTrashed()->whereNotNull('next_send_date')->cursor()->each(function ($recurring_invoice) {

            // $offset = $recurring_invoice->client->timezone_offset();
            // $re = Carbon::parse($recurring_invoice->next_send_date)->subSeconds($offset)->format('Y-m-d');
            $re = Carbon::parse($recurring_invoice->next_send_date)->format('Y-m-d');
            $recurring_invoice->next_send_date_client = $re;
            $recurring_invoice->saveQuietly();
        });

        RecurringExpense::withTrashed()->whereNotNull('next_send_date')->cursor()->each(function ($recurring_expense) {
            $recurring_expense->next_send_date_client = $recurring_expense->next_send_date;
            $recurring_expense->saveQuietly();
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
