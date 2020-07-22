<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Events\Quote;

use App\Models\Company;
use App\Models\Quote;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuoteWasApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $contact;

    public $quote;

    public $company;

    public $event_vars;
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(ClientContact $contact, Quote $quote, Company $company, array $event_vars)
    {
        $this->contact = $contact;
        $this->quote = $quote;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
