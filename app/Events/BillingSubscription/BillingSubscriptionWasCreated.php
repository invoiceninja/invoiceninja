<?php

namespace App\Events\BillingSubscription;

use App\Models\BillingSubscription;
use App\Models\Company;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BillingSubscriptionWasCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var BillingSubscription
     */
    public $billing_subscription;

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
    public function __construct(BillingSubscription $billing_subscription, Company $company, array $event_vars)
    {
        $this->billing_subscription = $billing_subscription;
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
        return new PrivateChannel('channel-name');
    }
}
