<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;

class SubdomainWasRemoved extends Event
{
    use SerializesModels;
    public $account;

    /**
     * Create a new event instance.
     *
     * @param $account
     */
    public function __construct($account)
    {
        $this->account = $account;
    }
}
