<?php

namespace App\Services\Quote;

use App\Events\Quote\QuoteWasMarkedSent;
use App\Models\Quote;

class MarkSent
{
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function run($quote)
    {

        /* Return immediately if status is not draft */
        if ($quote->status_id != Quote::STATUS_DRAFT) {
            return $quote;
        }

        $quote->markInvitationsSent();

        event(new QuoteWasMarkedSent($quote, $quote->company));

        $quote->service()->setStatus(Quote::STATUS_SENT)->applyNumber()->save();

        return $quote;

    }
}
