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

namespace App\Listeners\RecurringInvoice;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class RecurringInvoiceArchivedActivity implements ShouldQueue
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

        $event->recurring_invoice->service()->deletePdf();

        $fields = new stdClass;
        $user_id = array_key_exists('user_id', $event->event_vars) ? $event->event_vars['user_id'] : $event->recurring_invoice->user_id;

        $fields->user_id = $user_id;
        $fields->recurring_invoice_id = $event->recurring_invoice->id;
        $fields->client_id = $event->recurring_invoice->client_id;
        $fields->company_id = $event->recurring_invoice->company_id;
        $fields->activity_type_id = Activity::ARCHIVE_RECURRING_INVOICE;

        $this->activity_repo->save($fields, $event->recurring_invoice, $event->event_vars);
    }
}
