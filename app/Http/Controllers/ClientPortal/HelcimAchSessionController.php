<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Exceptions\PaymentFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\HelcimPaymentDriver;
use Illuminate\Http\JsonResponse;

class HelcimAchSessionController extends Controller
{
    public function __invoke(PaymentResponseRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'checkout_fingerprint' => ['required', 'string', 'size:64'],
        ]);
        $paymentHash = $request->getPaymentHash()->loadMissing('fee_invoice');
        $contact = auth()->guard('contact')->user();

        $gateway = CompanyGateway::query()
            ->whereKey($request->input('company_gateway_id'))
            ->where('company_id', $paymentHash->fee_invoice->company_id)
            ->where('gateway_key', 'ca3b3f7e4be811c96a8a1f4cafe2a97f')
            ->firstOrFail();

        /** @var HelcimPaymentDriver $driver */
        $driver = $gateway
            ->driver($contact->client)
            ->setPaymentMethod(GatewayType::BANK_TRANSFER)
            ->setPaymentHash($paymentHash)
            ->init();

        try {
            $session = $driver->initializeAchPaymentCheckout($validated['checkout_fingerprint']);
        } catch (PaymentFailed $e) {
            $status = in_array($e->getCode(), [400, 409], true) ? $e->getCode() : 400;

            return response()->json(['message' => $e->getMessage()], $status);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Failed to initialize the Helcim ACH checkout. Please try again.',
            ], 502);
        }

        return response()->json([
            'checkout_token' => $session['checkoutToken'],
            'secret_token' => $session['secretToken'],
        ]);
    }
}
