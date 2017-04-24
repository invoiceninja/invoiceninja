<?php

namespace App\Events;

use App\Models\Client;
use Illuminate\Queue\SerializesModels;

/**
 * Class ClientWasUpdated.
 */
class ClientWasUpdated extends Event
{
    use SerializesModels;

    /**
     * @var Client
     */
    public $client;

    /**
     * Create a new event instance.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }
}
