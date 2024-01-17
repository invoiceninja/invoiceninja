<?php

namespace App\Events\Subscription;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionWasCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var Subscription
     */
    public $subscription;

    /**
     * @var Company
     */
    public $company;

    /**
     * @var array
     */
    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Subscription $subscription, Company $company, array $event_vars)
    {
        $this->subscription = $subscription;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return [];
    }
}
