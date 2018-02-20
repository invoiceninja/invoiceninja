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
        if(env("CLOUDFLARE_DNS_ENABLED"))
            Cloudflare::addDNSRecord($event->account);
    }

    public function removeDNSRecord(SubdomainWasRemoved $event)
    {
        if(env("CLOUDFLARE_DNS_ENABLED"))
            Cloudflare::removeDNSRecord($event->account);
    }

}
