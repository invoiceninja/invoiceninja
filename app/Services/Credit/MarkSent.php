<?php

namespace App\Services\Credit;

use App\Events\Credit\CreditWasMarkedSent;
use App\Models\Credit;

class MarkSent
{
    private $client;

    private $credit;

    public function __construct($client, $credit)
    {
        $this->client = $client;
        $this->credit = $credit;
    }

    public function run()
    {

        /* Return immediately if status is not draft */
        if ($this->credit->status_id != Credit::STATUS_DRAFT) {
            return $this->credit;
        }

        $this->credit->markInvitationsSent();

        event(new CreditWasMarkedSent($this->credit, $this->credit->company));

        $this->credit->service()->setStatus(Credit::STATUS_SENT)->applyNumber()->save();

        return $this->credit;
    }
}
