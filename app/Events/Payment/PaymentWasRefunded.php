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

namespace App\Events\Payment;

use App\Models\Payment;
use Illuminate\Queue\SerializesModels;

/**
 * Class PaymentWasRefunded.
 */
class PaymentWasRefunded
{
    use SerializesModels;

    /**
     * @var Payment
     */
    public $payment;

    public $refund_amount;

    public $company;
    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     * @param $refund_amount
     */
    public function __construct(Payment $payment, $refund_amount, $company)
    {
        $this->payment = $payment;
        $this->refund_amount = $refund_amount;
        $this->company = $company;
    }
}
