<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits\Invoice\Broadcasting;

use App\Models\BaseModel;
use App\Transformers\ArraySerializer;
use Illuminate\Broadcasting\PrivateChannel;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

trait DefaultResourceBroadcast
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("company-{$this->company->company_key}"),
        ];
    }

    public function broadcastModel(): BaseModel
    {
        throw new \LogicException('Make sure to pass a model to the broadcastModel method.');
    }

    public function broadcastManager(Manager $manager): Manager
    {
        return $manager;
    }

    public function broadcastWith(): array
    {
        $entity = $this->broadcastModel();

        $manager = new Manager();

        $manager->setSerializer(new ArraySerializer());

        $manager->parseIncludes($this->broadcastIncludes());

        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($entity));

        $transformer = new $class();

        $resource = new Item($entity, $transformer, $entity->getEntityType());

        $manager = $this->broadcastManager($manager);

        $data = $manager->createData($resource)->toArray();

        $data = [...$data];

        if (\property_exists($this, 'socket')) {
            $data['x-socket-id'] = $this->socket;
        }

        return $data;
    }
}
