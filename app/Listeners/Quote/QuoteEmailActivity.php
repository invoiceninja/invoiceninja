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

namespace App\Listeners\Quote;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class QuoteEmailActivity implements ShouldQueue
{
    public $delay = 5;

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

        $fields = new stdClass;

        $user_id = array_key_exists('user_id', $event->event_vars) ? $event->event_vars['user_id'] : $event->invitation->quote->user_id;

        $fields->user_id = $user_id;

        $fields->quote_id = $event->invitation->quote->id;
        $fields->client_id = $event->invitation->quote->client_id;
        $fields->company_id = $event->invitation->quote->company_id;
        $fields->client_contact_id = $event->invitation->quote->client_contact_id;
        $fields->client_id = $event->invitation->quote->client_id;
        $fields->activity_type_id = Activity::EMAIL_QUOTE;

        $this->activity_repo->save($fields, $event->invitation->quote, $event->event_vars);
    }
}
