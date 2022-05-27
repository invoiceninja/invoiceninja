<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;


use App\Models\ScheduledJob;
use App\Utils\Traits\MakesHash;

class ScheduledJobTransformer extends EntityTransformer
{
    use MakesHash;

    public function transform(ScheduledJob $job)
    {
        return [
            'id' => $this->encodePrimaryKey($job->id),
            'action_name' => (string)$job->action_name,
            'parameters' => (array)$job->parameters
        ];
    }
}
