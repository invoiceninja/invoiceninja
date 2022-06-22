<?php

namespace App\Listeners;

use App\Events\SubdomainWasRemoved;
use App\Events\SubdomainWasUpdated;
use App\Ninja\DNS\Cloudflare;

/**
 * Class DNSListener.
 */
class DNSListener
{
    /**
     * @param DNSListener $event
     */

    public function addDNSRecord(SubdomainWasUpdated $event)
    {
    }

    public function removeDNSRecord(SubdomainWasRemoved $event)
    {
    }

}
