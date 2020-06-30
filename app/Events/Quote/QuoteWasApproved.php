<?php

namespace App\Events\Quote;

use App\Models\Quote;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuoteWasApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $quote;

    public $company;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Quote $quote, $company)
    {
        $this->quote = $quote;
        $this->company = $company;
    }
}
