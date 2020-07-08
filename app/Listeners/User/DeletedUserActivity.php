<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
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
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeletedUserActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

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
         MultiDB::setDb($event->company->db);

       $fields = new \stdClass;

        if (auth()->user()->id) {
            $fields->user_id = auth()->user()->id;
        } else {
            $fields->user_id = $event->user->id;
        }
        
        $fields->company_id = $event->company->id;
        $fields->activity_type_id = Activity::DELETE_USER;

        $this->activityRepo->save($fields, $event->user, $event->event_vars);
    }
}
