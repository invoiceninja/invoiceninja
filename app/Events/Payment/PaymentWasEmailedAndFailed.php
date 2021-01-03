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

namespace App\Events\Payment;

use App\Models\Company;
use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Class PaymentWasEmailedAndFailed.
 */
class PaymentWasEmailedAndFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Payment
     */
    public $payment;

    public $errors;

    public $company;

    public $event_vars;

    /**
     * PaymentWasEmailedAndFailed constructor.
     * @param Payment $payment
     * @param $company
     * @param array $errors
     * @param array $event_vars
     */
    public function __construct(Payment $payment, Company $company, array $errors, array $event_vars)
    {
        $this->payment = $payment;

        $this->errors = $errors;

        $this->company = $company;

        $this->event_vars = $event_vars;
    }
}
