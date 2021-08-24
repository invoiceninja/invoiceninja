<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Cron;

use App\Jobs\RecurringInvoice\SendRecurring;
use App\Libraries\MultiDB;
use App\Models\RecurringExpense;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

class RecurringExpensesCron
{
    use Dispatchable;

    public $tries = 1;
    
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {
        /* Get all expenses where the send date is less than NOW + 30 minutes() */
        nlog("Sending recurring expenses ".Carbon::now()->format('Y-m-d h:i:s'));

        if (! config('ninja.db.multi_db_enabled')) {

            $this->getRecurringExpenses();

        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->getRecurringExpenses();
            }
        }
    }

    private function getRecurringExpenses()
    {
        $recurring_expenses = RecurringExpense::where('next_send_date', '<=', now()->toDateTimeString())
                                                    ->whereNotNull('next_send_date')
                                                    ->whereNull('deleted_at')
                                                    ->where('remaining_cycles', '!=', '0')
                                                    // ->whereHas('client', function ($query) {
                                                    //      $query->where('is_deleted',0)
                                                    //            ->where('deleted_at', NULL);
                                                    // })
                                                    ->with('company')
                                                    ->cursor();

        nlog(now()->format('Y-m-d') . ' Generating Recurring Expenses. Count = '.$recurring_expenses->count());

        $recurring_expenses->each(function ($recurring_expense, $key) {
            nlog("Current date = " . now()->format("Y-m-d") . " Recurring date = " .$recurring_expense->next_send_date);

            if (!$recurring_expense->company->is_disabled) {
                $this->generateExpense($recurring_expense);
            }
        });
    }

    private function generateExpense(RecurringExpense $recurring_expense)
    {

    }
    
}
