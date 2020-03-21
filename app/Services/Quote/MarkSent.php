<?php

namespace App\Services\Quote;

use App\Events\Quote\QuoteWasMarkedSent;
use App\Models\Quote;

class MarkSent
{
    private $client;

    private $quote;

    public function __construct($client, $quote)
    {
        $this->client = $client;
        $this->quote = $quote;
    }

    public function run()
    {

        /* Return immediately if status is not draft */
        if ($this->quote->status_id != Quote::STATUS_DRAFT) {
            return $quote;
        }

        $this->quote->markInvitationsSent();

        event(new QuoteWasMarkedSent($this->quote, $this->quote->company));

        $this->quote->service()->setStatus(Quote::STATUS_SENT)->applyNumber()->save();

        return $this->quote;
    }
}
