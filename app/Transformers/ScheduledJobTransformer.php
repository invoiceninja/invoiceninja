<?php


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
            'action_name' => $job->action_name,
            'parameters' => $job->parameters
        ];
    }
}
