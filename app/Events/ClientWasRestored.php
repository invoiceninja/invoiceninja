<?php namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;

class ClientWasRestored extends Event
{
    use SerializesModels;

    public $client;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($client)
    {
        $this->client = $client;
    }
}
