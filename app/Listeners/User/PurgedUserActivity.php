<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\User;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class PurgedUserActivity implements ShouldQueue
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

        $fields = new stdClass();

        $fields->user_id = $event->admin_user->id;
        $fields->company_id = $event->company->id;
        $fields->activity_type_id = Activity::PURGE_USER;
        $fields->account_id = $event->company->account_id;
        $fields->notes = $event->purged_user_name;
        
        $this->activityRepo->save($fields, $event->admin_user, $event->event_vars);
    }
}
