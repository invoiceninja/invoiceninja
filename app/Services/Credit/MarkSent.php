<?php

namespace App\Services\Credit;

use App\Events\Credit\CreditWasMarkedSent;
use App\Models\Credit;

class MarkSent
{
    private $client;

    public function __construct($client)
    {
        $this->client = $client;
    }

    public function run($credit)
    {

        /* Return immediately if status is not draft */
        if ($credit->status_id != Credit::STATUS_DRAFT) {
            return $credit;
        }

        $credit->markInvitationsSent();

        event(new CreditWasMarkedSent($credit, $credit->company));

        $credit->service()->setStatus(Credit::STATUS_SENT)->applyNumber()->save();

        return $credit;

    }
}
