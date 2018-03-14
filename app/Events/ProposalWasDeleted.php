<?php

namespace App\Events;

use App\Models\Proposal;
use Illuminate\Queue\SerializesModels;

/**
 * Class ProposalWasDeleted.
 */
class ProposalWasDeleted extends Event
{
    use SerializesModels;

    /**
     * @var Proposal
     */
    public $proposal;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     */
    public function __construct(Proposal $proposal)
    {
        $this->proposal = $proposal;
    }
}
