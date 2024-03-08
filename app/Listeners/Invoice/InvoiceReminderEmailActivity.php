<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Invoice;

use App\Libraries\MultiDB;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class InvoiceReminderEmailActivity implements ShouldQueue
{
    protected $activity_repo;

    public $delay = 5;

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

        $fields = new stdClass();

        $user_id = isset($event->event_vars['user_id']) ? $event->event_vars['user_id'] : $event->invitation->invoice->user_id;

        $reminder = match($event->template) {
            'reminder1' => 63,
            'reminder2' => 64,
            'reminder3' => 65,
            'reminder_endless' => 66,
            'endless_reminder' => 66,
            default => 6,
        };

        $fields->user_id = $user_id;
        $fields->invoice_id = $event->invitation->invoice_id;
        $fields->company_id = $event->invitation->company_id;
        $fields->client_contact_id = $event->invitation->client_contact_id;
        $fields->client_id = $event->invitation->invoice->client_id;
        $fields->activity_type_id = $reminder;

        $this->activity_repo->save($fields, $event->invitation, $event->event_vars);
    }
}
