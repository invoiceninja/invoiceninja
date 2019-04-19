<?php

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

    public $refundAmount;

    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     * @param $refundAmount
     */
    public function __construct(Payment $payment, $refundAmount)
    {
        $this->payment = $payment;
        $this->refundAmount = $refundAmount;
    }
}
