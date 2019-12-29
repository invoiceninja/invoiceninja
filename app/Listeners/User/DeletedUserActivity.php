<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\User;

use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DeletedUserActivity
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

        if (auth()->user()->id) {
            $fields->user_id = auth()->user()->id;
        } else {
            $fields->user_id = $event->user->id;
        }
        
        $fields->company_id = $event->user->company_id;
        $fields->activity_type_id = Activity::DELETE_USER;

        $this->activityRepo->save($fields, $event->user);
    }
}
