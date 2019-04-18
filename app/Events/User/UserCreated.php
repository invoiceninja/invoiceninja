<?php

namespace App\Events\User;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Class UserCreated
 * @package App\Events\User
 */
class UserCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var $user
     */
    public $user;

    /**
     *  @var $company
     */
    
    public $company;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($user, $company)
    {
        $this->user = $user;
        $this->company = $company;
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
