<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;

/**
 * Class UserLoggedIn.
 */
class UserLoggedIn extends Event
{
    use SerializesModels;

    /**
     * UserLoggedIn constructor.
     */
    public function __construct()
    {
    }
}
