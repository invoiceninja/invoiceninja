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

namespace App\Events\Invoice;

use App\Models\Company;
use App\Models\InvoiceInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use League\Fractal\Manager;
use App\Transformers\ArraySerializer;
use League\Fractal\Resource\Item;

/**
 * Class InvoiceWasViewed.
 */
class InvoiceWasViewed implements ShouldBroadcast
{
    use SerializesModels;
    use InteractsWithSockets;

    /**
     * Create a new event instance.
     *
     * @param InvoiceInvitation $invitation
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(public InvoiceInvitation $invitation, public Company $company, public array $event_vars)
    {
        //
    }

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
        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($this->invitation->invoice));

        $transformer = new $class();

        $resource = new Item($this->invitation->invoice, $transformer, $this->invitation->invoice->getEntityType());

        $manager->parseIncludes('client');

        $data = $manager->createData($resource)->toArray();

        return $data;
    }
}
