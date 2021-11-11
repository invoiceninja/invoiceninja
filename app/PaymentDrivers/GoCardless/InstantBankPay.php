<?php

namespace App\PaymentDrivers\GoCardless;

use Illuminate\Http\Request;
use App\PaymentDrivers\Common\MethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class InstantBankPay implements MethodInterface
{
    public function authorizeView(array $data) { }

    public function authorizeResponse(Request $request) { }

    public function paymentView(array $data) { }

    public function paymentResponse(PaymentResponseRequest $request) { }
}
