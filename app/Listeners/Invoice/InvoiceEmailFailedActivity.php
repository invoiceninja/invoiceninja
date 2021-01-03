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

namespace App\Listeners\Invoice;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class InvoiceEmailFailedActivity implements ShouldQueue
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

        $fields->invoice_id = $event->invoice->id;
        $fields->client_id = $event->invoice->client_id;
        $fields->user_id = $event->invoice->user_id;
        $fields->company_id = $event->invoice->company_id;
        $fields->activity_type_id = Activity::EMAIL_INVOICE_FAILED;
        $fields->notes = $event->errors;

        $this->activity_repo->save($fields, $event->invoice, $event->event_vars);
    }
}
