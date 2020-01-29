<?php

namespace App\Events\Credit;

use App\Models\Credit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditWasEmailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $credit;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Credit $credit)
    {
        $this->credit = $credit;
    }
}
