<?php

namespace App\Services\Quote;

use App\Events\Quote\QuoteWasMarkedApproved;
use App\Models\Quote;

class MarkApproved
{
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function __invoke($quote)
    {
        /* Return immediately if status is not draft */
        if ($quote->status_id != Quote::STATUS_DRAFT) {
            return $quote;
        }

        $quote->service()->setStatus(Quote::STATUS_APPROVED)->applyNumber()->save();

        event(new QuoteWasMarkedApproved($quote, $quote->company));

        return $quote;
    }
}
