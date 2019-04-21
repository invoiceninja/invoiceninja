<?php

namespace App\Listeners\Client;

use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreatedClientActivity
{
    protected $activityRepo;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ActivityRepository $activityRepo)
    {
        $this->activityRepo = $activityRepo;
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {

        $fields = new \stdClass;

        $fields->client_id = $event->client->id;
        $fields->user_id = $event->client->user_id;
        $fields->company_id = $event->client->company_id;
        $fields->activity_type_id = Activity::CREATE_CLIENT;

        $this->activityRepo->save($fields, $event->client);
    }
}
