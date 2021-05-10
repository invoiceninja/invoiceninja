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

namespace App\Listeners\RecurringInvoice;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class CreateRecurringInvoiceActivity implements ShouldQueue
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

        $fields = new stdClass;

        $fields->recurring_invoice_id = $event->recurring_invoice_id->id;
        $fields->client_id = $event->recurring_invoice_id->client_id;
        $fields->user_id = $event->recurring_invoice_id->user_id;
        $fields->company_id = $event->recurring_invoice_id->company_id;
        $fields->activity_type_id = Activity::CREATE_RECURRING_INVOICE;

        $this->activity_repo->save($fields, $event->recurring_invoice_id, $event->event_vars);
    }
}
