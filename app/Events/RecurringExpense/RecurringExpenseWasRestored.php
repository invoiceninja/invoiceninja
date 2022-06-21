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

namespace App\Events\RecurringExpense;

use App\Models\Company;
use App\Models\RecurringExpense;
use Illuminate\Queue\SerializesModels;

/**
 * Class RecurringExpenseWasRestored.
 */
class RecurringExpenseWasRestored
{
    use SerializesModels;

    /**
     * @var RecurringExpense
     */
    public $recurring_expense;

    public $company;

    public $event_vars;

    public $fromDeleted;

    /**
     * Create a new event instance.
     *
     * @param RecurringExpense $recurring_expense
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(RecurringExpense $recurring_expense, $fromDeleted, Company $company, array $event_vars)
    {
        $this->recurring_expense = $recurring_expense;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
