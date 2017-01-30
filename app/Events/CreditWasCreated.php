<?php

namespace App\Events;

use App\Models\Credit;
use Illuminate\Queue\SerializesModels;

class CreditWasCreated extends Event
{
    use SerializesModels;

    /**
     * @var Credit
     */
    public $credit;

    /**
     * Create a new event instance.
     *
     * @param Credit $credit
     */
    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }
}
