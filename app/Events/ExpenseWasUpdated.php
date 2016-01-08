<?php namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;

class ExpenseWasUpdated extends Event
{
    use SerializesModels;

    public $expense;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($expense)
    {
        $this->expense = $expense;
    }
}
