<?php


namespace App\Transformers;


use App\Models\ScheduledJob;
use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;

class TaskSchedulerTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
        'job'
    ];

    public function includeJob(Scheduler $scheduler)
    {
        $transformer = new ScheduledJobTransformer($this->serializer);

        return $this->item($scheduler->job, $transformer, ScheduledJob::class);
    }

    public function transform(Scheduler $scheduler)
    {
        return [
            'id' => $this->encodePrimaryKey($scheduler->id),
            'company_id' => $this->encodePrimaryKey($scheduler->user_id),
            'paused' => $scheduler->paused,
            'archived' => $scheduler->archived,
            'repeat_every' => $scheduler->repeat_every,
            'start_from' => $scheduler->start_from,
            'scheduled_run' => $scheduler->scheduled_run,
        ];
    }

}
