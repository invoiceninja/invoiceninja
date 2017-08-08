<?php

namespace App\Events;

use App\Models\Invitation;
use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceInvitationWasViewed.
 */
class InvoiceInvitationWasViewed extends Event
{
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invoice;

    /**
     * @var Invitation
     */
    public $invitation;

    /**
     * Create a new event instance.
     *
     * @param Invoice    $invoice
     * @param Invitation $invitation
     */
    public function __construct(Invoice $invoice, Invitation $invitation)
    {
        $this->invoice = $invoice;
        $this->invitation = $invitation;
    }
}
