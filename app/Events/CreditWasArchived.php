<?php

namespace App\Events;

use App\Models\Credit;
use Illuminate\Queue\SerializesModels;

/**
 * Class CreditWasArchived.
 */
class CreditWasArchived extends Event
{
    use SerializesModels;

    /**
     * @var Client
     */
    public $credit;

    /**
     * Create a new event instance.
     *
     * @param Client $credit
     */
    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }
}
