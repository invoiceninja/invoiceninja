<?php
/**
 * Credit Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://creditninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Credit;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class CreditViewedActivity implements ShouldQueue
{
    protected $activity_repo;

    /**
     * Create the event listener.
     *
     * @param ActivityRepository $activity_repo
     */
    public function __construct(ActivityRepository $activity_repo)
    {
        $this->activity_repo = $activity_repo;
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

        $event->invitation->credit->service()->markSent()->save();

        $fields = new stdClass();

        $user_id = isset($event->event_vars['user_id']) ? $event->event_vars['user_id'] : $event->invitation->user_id;

        $fields->user_id = $user_id;
        $fields->company_id = $event->invitation->company_id;
        $fields->activity_type_id = Activity::VIEW_CREDIT;
        $fields->client_id = $event->invitation->credit->client_id;
        $fields->client_contact_id = $event->invitation->client_contact_id;
        $fields->invitation_id = $event->invitation->id;
        $fields->credit_id = $event->invitation->credit_id;

        $this->activity_repo->save($fields, $event->invitation->credit, $event->event_vars);
    }
}
