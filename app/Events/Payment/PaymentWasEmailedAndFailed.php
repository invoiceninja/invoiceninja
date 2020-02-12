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
 * Class InvoiceWasEmailedAndFailed.
 */
class PaymentWasEmailedAndFailed
{
    use SerializesModels;

    /**
     * @var Payment
     */
    public $payment;

    /**
     * @var array
     */
    public $errors;

    /**
     * PaymentWasEmailedAndFailed constructor.
     * @param Payment $payment
     * @param array $errors
     */
    public function __construct(Payment $payment, array $errors)
    {
        $this->payment = $payment;

        $this->errors = $errors;
    }
}
