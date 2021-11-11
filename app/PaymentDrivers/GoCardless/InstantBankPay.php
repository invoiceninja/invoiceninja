<?php

namespace App\PaymentDrivers\GoCardless;

use Illuminate\Http\Request;
use App\PaymentDrivers\Common\MethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\GoCardlessPaymentDriver;

class InstantBankPay implements MethodInterface
{
    protected GoCardlessPaymentDriver $go_cardless;

    public function __construct(GoCardlessPaymentDriver $go_cardless)
    {
        $this->go_cardless = $go_cardless;

        $this->go_cardless->init();
    }

    public function authorizeView(array $data) { }

    public function authorizeResponse(Request $request) { }

    public function paymentView(array $data) { }

    public function paymentResponse(PaymentResponseRequest $request) { }
}
