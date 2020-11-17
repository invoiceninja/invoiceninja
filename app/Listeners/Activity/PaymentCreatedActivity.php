<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Activity;

use App\Jobs\Invoice\InvoiceWorkflowSettings;
use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use stdClass;

class PaymentCreatedActivity implements ShouldQueue
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

        $payment = $event->payment;

        $invoices = $payment->invoices;

        $fields = new stdClass;

        $fields->payment_id = $payment->id;
        $fields->client_id = $payment->client_id;
        $fields->user_id = $payment->user_id;
        $fields->company_id = $payment->company_id;
        $fields->activity_type_id = Activity::CREATE_PAYMENT;

        if (count($invoices) == 0) {
            $this->activity_repo->save($fields, $payment, $event->event_vars);
        }
    }
}
