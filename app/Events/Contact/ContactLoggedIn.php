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

namespace App\Events\Contact;

use App\Models\ClientContact;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Class UserLoggedIn.
 */
class ContactLoggedIn
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $client_contact;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param ClientContact $client_contact
     * @param $company
     * @param $event_vars
     */
    public function __construct(ClientContact $client_contact, $company, $event_vars)
    {
        $this->client_contact = $client_contact;
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
