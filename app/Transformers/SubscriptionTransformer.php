<?php

namespace App\Transformers;

use App\Models\Subscription;
use App\Utils\Traits\MakesHash;

class SubscriptionTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [];

    /**
     * @var array
     */
    protected $availableIncludes = [];

    /**
     * @param Activity $subscription
     *
     * @return array
     */
    public function transform(Subscription $subscription)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($subscription->id),
            'company_id' => (string) $this->encodePrimaryKey($subscription->company_id),
            'user_id' => (string) $this->encodePrimaryKey($subscription->user_id),
            'archived_at' => (int)$subscription->deleted_at,
            'updated_at' => (int)$subscription->updated_at,
            'created_at' => (int)$subscription->created_at,
            'is_deleted' => (bool)$subscription->is_deleted,
            'target_url' => $subscription->target_url ? (string) $subscription->target_url : '',
            'event_id' => (string) $subscription->event_id,
            'format' => (string) $subscription->format,
        ];
    }
}
