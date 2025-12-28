<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\Models\Client;
use App\Models\SystemLog;

/**
 * Class SystemLogTrait.
 */
trait SystemLogTrait
{
    public function sysLog($log, $category_id = SystemLog::CATEGORY_GATEWAY_RESPONSE, $event_id = SystemLog::EVENT_GATEWAY_FAILURE, Client $client = null)
    {
        if ($client != null) {
            $this->client = $client;
        }

        $sl = [
            'client_id' => $this->client->id,
            'company_id' => $this->client->company->id,
            'user_id' => $this->client->user_id,
            'log' => $log,
            'category_id' => $category_id,
            'event_id' => $event_id,
        ];

        SystemLog::create($sl);
    }
}
