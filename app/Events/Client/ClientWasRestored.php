<?php

namespace App\Events\Client;

use App\Models\Client;
use Illuminate\Queue\SerializesModels;

/**
 * Class ClientWasRestored.
 */
class ClientWasRestored
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
