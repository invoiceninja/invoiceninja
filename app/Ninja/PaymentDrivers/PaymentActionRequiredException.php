<?php

namespace App\Ninja\PaymentDrivers;

use Throwable;

/**
 * Thrown when Stripe requires further user intervention to process a charge.
 * Allows the calling code to handle the exception by requesting further interaction from the user.
 *
 * Class StripeActionRequiredException
 * @package App\Ninja\PaymentDrivers
 */
class PaymentActionRequiredException extends \Exception
{
    protected $data;

    public function __construct(
        $data,
        $message = "Direct user approval required.",
        $code = 0,
        Throwable $previous = null
    ) {
        $this->data = $data;
        parent::__construct($message, $code, $previous);
    }

    public function getData()
    {
        return $this->data;
    }
}
