<?php

namespace App\Events\Payment\Methods;

use App\Models\ClientGatewayToken;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MethodDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var ClientGatewayToken
     */
    private $payment_method;

    /**
     * Create a new event instance.
     *
     * @param ClientGatewayToken $payment_method
     */
    public function __construct(ClientGatewayToken $payment_method)
    {
        $this->payment_method = $payment_method;
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
