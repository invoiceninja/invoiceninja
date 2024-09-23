<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Client;

use App\Models\Client;
use App\Models\Company;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use Illuminate\Broadcasting\Channel;
use App\Transformers\ArraySerializer;
use Illuminate\Queue\SerializesModels;
use App\Transformers\ClientTransformer;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

/**
 * Class ClientWasArchived.
 */
class ClientWasArchived implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var Client
     */
    public $client;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Client $client
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Client $client, Company $company, array $event_vars)
    {
        $this->client = $client;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }

    public function broadcastWith()
    {

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($this->client));

        $transformer = new $class();

        $resource = new Item($this->client, $transformer, $this->client->getEntityType());
        $data = $manager->createData($resource)->toArray();

        return $data;

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {

        return [
            new PrivateChannel("company-{$this->company->company_key}"),
        ];

    }

}
