<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Payment\Methods;

use App\Models\ClientGatewayToken;
use App\Models\Company;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MethodDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var ClientGatewayToken
     */
    public $payment_method;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param ClientGatewayToken $payment_method
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(ClientGatewayToken $payment_method, Company $company, array $event_vars)
    {
        $this->payment_method = $payment_method;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return [];
    }
}
