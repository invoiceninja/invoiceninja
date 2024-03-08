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

namespace App\Events\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasCreated.
 */
class InvoiceWasCreated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invoice;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Invoice $invoice, Company $company, array $event_vars)
    {
        $this->invoice = $invoice;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return PrivateChannel|array
     */
    public function broadcastOn()
    {
        return [];
    }


    // /**
    //  * Get the data to broadcast.
    //  *
    //  * @return array<string, mixed>
    //  */
    // public function broadcastWith(): array
    // {
    //     return ['id' => 'value'];
    // }
}
