<?php

namespace App\Events\ClientSubscription;

use App\Models\ClientSubscription;
use App\Models\Company;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientSubscriptionWasCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var ClientSubscription
     */
    public $client_subscription;

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
    public function __construct(ClientSubscription $client_subscription, Company $company, array $event_vars)
    {
        $this->client_subscription = $client_subscription;
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
