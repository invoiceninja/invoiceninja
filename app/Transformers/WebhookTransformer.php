<?php

namespace App\Transformers;

use App\Models\Webhook;
use App\Utils\Traits\MakesHash;

class WebhookTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [];

    /**
     * @var array
     */
    protected array $availableIncludes = [];

    /**
     * @param Webhook $webhook
     *
     * @return array
     */
    public function transform(Webhook $webhook)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($webhook->id),
            'company_id' => (string) $this->encodePrimaryKey($webhook->company_id),
            'user_id' => (string) $this->encodePrimaryKey($webhook->user_id),
            'archived_at' => (int) $webhook->deleted_at,
            'updated_at' => (int) $webhook->updated_at,
            'created_at' => (int) $webhook->created_at,
            'is_deleted' => (bool) $webhook->is_deleted,
            'target_url' => $webhook->target_url ? (string) $webhook->target_url : '',
            'event_id' => (string) $webhook->event_id,
            'format' => (string) $webhook->format,
            'rest_method' => (string) $webhook->rest_method ?: '',
            'headers' => $webhook->headers ?: [],
        ];
    }
}
