<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Listeners\Activity;

use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PaymentDeletedActivity implements ShouldQueue
{
    protected $activityRepo;
    /**
     * Create the event listener.
     *
     * @return void
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
        $payment = $event->payment;

        $invoices = $payment->invoices;
        
        $fields = new \stdClass;

        $fields->payment_id = $payment->id;
        $fields->user_id = $payment->user_id;
        $fields->company_id = $payment->company_id;
        $fields->activity_type_id = Activity::DELETE_PAYMENT;


        foreach ($invoices as $invoice) { //todo we may need to add additional logic if in the future we apply payments to other entity Types, not just invoices
            $fields->invoice_id = $invoice->id;

            $this->activityRepo->save($fields, $invoice);
        }

        if (count($invoices) == 0) {
            $this->activityRepo->save($fields, $payment);
        }
    }
}
