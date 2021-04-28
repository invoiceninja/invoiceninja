<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\User;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use stdClass;

class DeletedUserActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected $activityRepo;

    /**
     * Create the event listener.
     *
     * @param ActivityRepository $activityRepo
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
        MultiDB::setDb($event->company->db);

        $fields = new stdClass;

        $fields->user_id = $event->creating_user->id;
        $fields->notes = $event->creating_user->present()->name() . " Deleted the user " . $event->user->present()->name();
        $fields->company_id = $event->company->id;
        $fields->activity_type_id = Activity::DELETE_USER;

        $this->activityRepo->save($fields, $event->user, $event->event_vars);
    }
}
