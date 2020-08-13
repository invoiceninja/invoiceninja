<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
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

class PaymentCreatedActivity implements ShouldQueue
{
    protected $activity_repo;
    /**
     * Create the event listener.
     *
     * @return void
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
        
        $fields = new \stdClass;

        $fields->payment_id = $payment->id;
        $fields->client_id = $payment->client_id;
        $fields->user_id = $payment->user_id;
        $fields->company_id = $payment->company_id;
        $fields->activity_type_id = Activity::CREATE_PAYMENT;

        /*todo tests fail for this for some reason?*/
        // foreach ($invoices as $invoice) { //todo we may need to add additional logic if in the future we apply payments to other entity Types, not just invoices
        //     $fields->invoice_id = $invoice->id;

        //     InvoiceWorkflowSettings::dispatchNow($invoice);

        //     $this->activity_repo->save($fields, $invoice, $event->event_vars);
        // }

        if (count($invoices) == 0) {
            $this->activity_repo->save($fields, $payment, $event->event_vars);
        }
    }
}
