<?php

namespace App\Events;

use App\Models\Expense;
use Illuminate\Queue\SerializesModels;

/**
 * Class ExpenseWasDeleted.
 */
class ExpenseWasDeleted extends Event
{
    use SerializesModels;

    /**
     * @var Expense
     */
    public $expense;

    /**
     * Create a new event instance.
     *
     * @param Expense $expense
     */
    public function __construct(Expense $expense)
    {
        $this->expense = $expense;
    }
}
