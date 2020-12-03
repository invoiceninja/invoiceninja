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

namespace App\Http\Controllers;

use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\Payment;
use Illuminate\Support\Arr;

class PaymentWebhookController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function __invoke(PaymentWebhookRequest $request, string $company_key = null, string $gateway_key = null)
    {
        $transaction_reference = $this->getTransactionReference($request->all(), $request);

        $payment = Payment::where('transaction_reference', $transaction_reference)->first();

        if (is_null($payment)) {
            return response([
                'message' => 'Sorry, we couldn\'t find requested payment.',
                'status_code' => 404,
            ], 404); /* Record event, throw an exception.. */
        }

        return $request
            ->companyGateway()
            ->driver($payment->client)
            ->setPaymentMethod($payment->gateway_type_id)
            ->processWebhookRequest($request->all(), $request->company(), $request->companyGateway(), $payment);
    }

    public function getTransactionReference(array $data, PaymentWebhookRequest $request)
    {
        $flatten = Arr::dot($data);

        if (isset($flatten['data.object.id'])) {
            return $flatten['data.object.id']; // stripe.com
        }

        if ($request->has('cko-session-id')) {
            // checkout.com
        }
    }
}
