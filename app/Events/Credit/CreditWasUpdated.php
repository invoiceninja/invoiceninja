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

namespace App\Events\Credit;

use App\Models\Company;
use App\Models\Credit;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use League\Fractal\Manager;
use App\Transformers\ArraySerializer;
use League\Fractal\Resource\Item;

class CreditWasUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $credit;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Credit $credit
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Credit $credit, Company $company, array $event_vars)
    {
        $this->credit = $credit;
        $this->company = $company;
        $this->event_vars = $event_vars;

        $this->dontBroadcastToCurrentUser();
    }

    /**
     * @inheritDoc
     */
    public function broadcastOn() 
    {
        return [
            new PrivateChannel("company-{$this->company->company_key}"),
        ];
    }

    /**
     * @inheritDoc
     */
    public function broadcastWith(): array
    {
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($this->credit));

        $transformer = new $class();

        $resource = new Item($this->credit, $transformer, $this->credit->getEntityType());
        $data = $manager->createData($resource)->toArray();

        return [...$data, 'x-socket-id' => $this->socket];
    }
}
