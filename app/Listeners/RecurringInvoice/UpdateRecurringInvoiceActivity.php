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

namespace App\Listeners\RecurringInvoice;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class UpdateRecurringInvoiceActivity implements ShouldQueue
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
        MultiDB::setDB($event->company->db);

        $fields = new stdClass();
        $user_id = isset($event->event_vars['user_id']) ? $event->event_vars['user_id'] : $event->recurring_invoice->user_id;

        $fields->user_id = $user_id;
        $fields->client_id = $event->recurring_invoice->client_id;
        $fields->company_id = $event->recurring_invoice->company_id;
        $fields->activity_type_id = Activity::UPDATE_RECURRING_INVOICE;
        $fields->recurring_invoice_id = $event->recurring_invoice->id;

        $this->activity_repo->save($fields, $event->recurring_invoice, $event->event_vars);
    }
}
