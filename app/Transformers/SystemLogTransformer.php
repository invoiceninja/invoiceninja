<?php

namespace App\Transformers;

use App\Models\SystemLog;
use App\Utils\Traits\MakesHash;

class SystemLogTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [];

    /**
     * @var array
     */
    protected array $availableIncludes = [];

    /**
     * @param SystemLog $system_log
     *
     * @return array
     */
    public function transform(SystemLog $system_log)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($system_log->id),
            'company_id' => (string) $this->encodePrimaryKey($system_log->company_id),
            'user_id' => (string) $this->encodePrimaryKey($system_log->user_id),
            'client_id' => (string) $this->encodePrimaryKey($system_log->client_id),
            'event_id' => (int) $system_log->event_id,
            'category_id' => (int) $system_log->category_id,
            'type_id' => (int) $system_log->type_id,
            'log' => json_encode($system_log->log),
            'updated_at' => (int) $system_log->updated_at,
            'created_at' => (int) $system_log->created_at,
        ];
    }
}
