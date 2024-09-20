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

namespace App\Utils\Traits\Invoice\Broadcasting;

use App\Transformers\ArraySerializer;
use Illuminate\Broadcasting\PrivateChannel;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

trait DefaultInvoiceBroadcast
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("company-{$this->company->company_key}"),
        ];
    }

    public function broadcastWith(): array
    {
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($this->invoice));

        $transformer = new $class();

        $resource = new Item($this->invoice, $transformer, $this->invoice->getEntityType());
        $data = $manager->createData($resource)->toArray();

        return $data;
    }
}
