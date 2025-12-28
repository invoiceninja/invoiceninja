<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Subscription;

use App\Models\Company;
use App\Models\Subscription;
use Illuminate\Queue\SerializesModels;

/**
 * Class SubscriptionWasArchived.
 */
class SubscriptionWasArchived
{
    use SerializesModels;

    /**
     * @var Subscription
     */
    public $subscription;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Subscription $subscription
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Subscription $subscription, Company $company, array $event_vars)
    {
        $this->subscription = $subscription;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
