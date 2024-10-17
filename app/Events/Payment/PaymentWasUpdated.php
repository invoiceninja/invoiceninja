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

namespace App\Events\Payment;

use App\Models\Company;
use App\Models\Payment;
use App\Transformers\ArraySerializer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

/**
 * Class PaymentWasUpdated.
 */
class PaymentWasUpdated implements ShouldBroadcast
{
    use SerializesModels, InteractsWithSockets;

    /**
     * @var Payment
     */
    public $payment;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Payment $payment, Company $company, array $event_vars)
    {
        $this->payment = $payment;
        $this->company = $company;
        $this->event_vars = $event_vars;

        $this->dontBroadcastToCurrentUser();
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
        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($this->payment));

        $transformer = new $class();

        $resource = new Item($this->payment, $transformer, $this->payment->getEntityType());
        $data = $manager->createData($resource)->toArray();

        return [...$data, 'x-socket-id' => $this->socket];
    }
}
