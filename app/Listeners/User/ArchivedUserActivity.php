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

namespace App\Listeners\User;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class ArchivedUserActivity implements ShouldQueue
{
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

        $user_id = array_key_exists('user_id', $event->event_vars) ? $event->event_vars['user_id'] : $event->creating_user->id;

        $fields->user_id = $user_id;
        $fields->notes = $event->creating_user->present()->name.' Archived User '.$event->user->present()->name();

        $fields->company_id = $event->company->id;
        $fields->activity_type_id = Activity::ARCHIVE_USER;

        $this->activityRepo->save($fields, $event->user, $event->event_vars);
    }
}
