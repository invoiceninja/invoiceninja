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

namespace App\Http\Controllers;

use App\Http\Requests\Terminal\AcknowledgeTerminalSurchargeRequest;
use App\Http\Requests\Terminal\ActionTerminalPaymentIntentRequest;
use App\Http\Requests\Terminal\FinalizeTerminalPaymentIntentRequest;
use App\Http\Requests\Terminal\QuoteTerminalPaymentRequest;
use App\Http\Requests\Terminal\SendTerminalReceiptRequest;
use App\Http\Requests\Terminal\ShowTerminalConfigRequest;
use App\Http\Requests\Terminal\StoreTerminalConnectionTokenRequest;
use App\Http\Requests\Terminal\StoreTerminalLocationRequest;
use App\Http\Requests\Terminal\StoreTerminalPaymentIntentRequest;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Utils\Ninja;

/* In-person card payments (Stripe Terminal) for the mobile apps */
class TerminalController extends BaseController
{
    public function config(ShowTerminalConfigRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $gateways = CompanyGateway::query()
            ->where('company_id', $user->company()->id)
            ->where('is_deleted', 0)
            ->get()
            ->filter(fn (CompanyGateway $company_gateway) => $company_gateway->supportsCardPresent())
            ->map(function (CompanyGateway $company_gateway) {
                $card_present = $company_gateway->driver()->cardPresent();

                try {
                    $account = $card_present->account();
                    $locations = $card_present->locations();
                } catch (\Throwable $e) {
                    nlog("terminal: {$company_gateway->hashed_id} unavailable - " . $e->getMessage());

                    return null;
                }

                $policy = $card_present->surcharge()->check(null, $account);
                $fees_and_limits = $company_gateway->getFeesAndLimits(GatewayType::CARD_PRESENT);

                return [
                    'company_gateway_id' => $company_gateway->hashed_id,
                    'label' => $company_gateway->label ?: $company_gateway->gateway->name,
                    'livemode' => $card_present->livemode(),
                    'account_country' => $account['country'],
                    'default_currency' => $account['default_currency'],
                    'card_payments_active' => $account['card_payments_active'],
                    'surcharge' => [
                        'allowed' => $policy['allowed'],
                        'reason' => $policy['reason'],
                        'fee_percent' => floatval($fees_and_limits->fee_percent ?? 0),
                        'fee_amount' => floatval($fees_and_limits->fee_amount ?? 0),
                    ],
                    'locations' => $locations,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'enabled' => (bool) config('ninja.terminal_enabled'),
            'plan_ok' => !(Ninja::isHosted() && $user->account->isFreeHostedClient()),
            'gateways' => $gateways,
        ]);
    }

    public function connectionToken(StoreTerminalConnectionTokenRequest $request, CompanyGateway $company_gateway)
    {
        return response()->json([
            'secret' => $company_gateway->driver()->cardPresent()->connectionToken($request->input('location_id')),
        ]);
    }

    public function storeLocation(StoreTerminalLocationRequest $request, CompanyGateway $company_gateway)
    {
        return response()->json($company_gateway->driver()->cardPresent()->createLocation());
    }

    public function acknowledgeSurcharge(AcknowledgeTerminalSurchargeRequest $request, CompanyGateway $company_gateway)
    {
        $company_gateway->driver()->cardPresent()->acknowledgeSurcharge(auth()->user());

        return response()->json(['acknowledged' => true]);
    }

    public function quote(QuoteTerminalPaymentRequest $request, Invoice $invoice)
    {
        return response()->json(
            $request->companyGateway()->driver($invoice->client)->cardPresent()->surcharge()->quote($invoice, $request->input('amount'), $request->input('apply_fee'))
        );
    }

    public function storePaymentIntent(StoreTerminalPaymentIntentRequest $request, Invoice $invoice)
    {
        return response()->json(
            $request->companyGateway()->driver($invoice->client)->cardPresent()->createPaymentIntent(auth()->user(), $invoice, [
                'amount' => floatval($request->input('amount')),
                'apply_fee' => (bool) $request->input('apply_fee'),
                'collection_id' => $request->input('collection_id'),
                'expected_total' => floatval($request->input('expected_total')),
            ])
        );
    }

    public function finalize(FinalizeTerminalPaymentIntentRequest $request)
    {
        $collection = $request->collection();

        return response()->json($collection->cardPresent()->finalize($collection));
    }

    public function complete(ActionTerminalPaymentIntentRequest $request)
    {
        $collection = $request->collection();

        return response()->json($collection->cardPresent()->record($collection));
    }

    public function cancel(ActionTerminalPaymentIntentRequest $request)
    {
        $collection = $request->collection();

        return response()->json($collection->cardPresent()->cancel($collection));
    }

    public function receipt(SendTerminalReceiptRequest $request)
    {
        $collection = $request->collection();

        $collection->cardPresent()->receipt($collection, $request->input('email'));

        return response()->json(['message' => ctrans('texts.emailed_payment')]);
    }
}
