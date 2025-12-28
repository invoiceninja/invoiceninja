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
 * Class SubscriptionWasRestored.
 */
class SubscriptionWasRestored
{
    use SerializesModels;

    /**
     * @var Subscription
     */
    public $subscription;

    public $company;

    public $event_vars;

    public $fromDeleted;

    /**
     * Create a new event instance.
     *
     * @param Subscription $subscription
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Subscription $subscription, $fromDeleted, Company $company, array $event_vars)
    {
        $this->subscription = $subscription;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
