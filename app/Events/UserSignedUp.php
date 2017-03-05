<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;

/**
 * Class UserSignedUp.
 */
class UserSignedUp extends Event
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
    }
}
