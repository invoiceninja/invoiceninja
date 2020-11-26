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

namespace App\Listeners\Payment;

use App\Libraries\MultiDB;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentEmailFailureActivity implements ShouldQueue
{
    use UserNotifies;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param object $event
     * @return bool
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $payment = $event->payment;

        info("i failed emailing {$payment->number}");
        // info(print_r($event->errors,1));
    }
}
