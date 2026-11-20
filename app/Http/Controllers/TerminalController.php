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

use App\Http\Requests\Terminal\ActionTerminalPaymentIntentRequest;
use App\Http\Requests\Terminal\FinalizeTerminalPaymentIntentRequest;
use App\Http\Requests\Terminal\QuoteTerminalPaymentRequest;
use App\Http\Requests\Terminal\SendTerminalReceiptRequest;
use App\Http\Requests\Terminal\ShowTerminalConfigRequest;
use App\Http\Requests\Terminal\StoreTerminalConnectionTokenRequest;
use App\Http\Requests\Terminal\StoreTerminalLocationRequest;
use App\Http\Requests\Terminal\StoreTerminalPaymentIntentRequest;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\PaymentDrivers\Stripe\CardPresent;
use App\Utils\Ninja;

/* In-person card payments (Stripe Terminal) for the mobile apps */
class TerminalController extends BaseController
{
    public function config(ShowTerminalConfigRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $company = $user->company();

        /* For a company with no gateway to set up: whether its country takes in-person payments at all */
        $company_country = $company->country()?->iso_3166_2;

        /* Switched off: nothing to ask Stripe */
        if (!config('ninja.terminal_enabled')) {
            return response()->json(['enabled' => false, 'plan_ok' => false, 'gateways' => [], 'setup_gateways' => [], 'company_country' => $company_country]);
        }

        $company_gateways = CompanyGateway::query()
            ->with('gateway')
            ->where('company_id', $company->id)
            ->where('is_deleted', 0)
            ->get();

        $gateways = $company_gateways
            ->filter(fn (CompanyGateway $company_gateway) => $company_gateway->supportsCardPresent())
            ->map(function (CompanyGateway $company_gateway) use ($company) {
                $card_present = $company_gateway->setRelation('company', $company)->driver()->cardPresent();

                try {
                    $account = $card_present->account();
                    $locations = $card_present->locations();
                } catch (\Throwable $e) {
                    nlog("terminal: {$company_gateway->hashed_id} unavailable - " . $e->getMessage());

                    return null;
                }

                return [
                    'company_gateway_id' => $company_gateway->hashed_id,
                    'label' => $company_gateway->label ?: $company_gateway->gateway->name,
                    'livemode' => $card_present->livemode(),
                    'account_country' => $account['country'],
                    'default_currency' => $account['default_currency'],
                    'card_payments_active' => $account['card_payments_active'],
                    'locations' => $locations,
                ];
            })
            ->filter()
            ->values();

        /*
         * Stripe gateways with Card present switched off, so the app can offer to set one up. Never in gateways:
         * nothing may collect on them. The app decides which countries it can take payments in.
         */
        $setup_gateways = $company_gateways
            ->filter(fn (CompanyGateway $company_gateway) => $company_gateway->canTakeCardPresent() && !$company_gateway->supportsCardPresent())
            ->map(function (CompanyGateway $company_gateway) use ($company) {
                try {
                    $account = $company_gateway->setRelation('company', $company)->driver()->cardPresent()->account();
                } catch (\Throwable $e) {
                    nlog("terminal: {$company_gateway->hashed_id} unavailable - " . $e->getMessage());

                    return null;
                }

                return [
                    'company_gateway_id' => $company_gateway->hashed_id,
                    'label' => $company_gateway->label ?: $company_gateway->gateway->name,
                    'account_country' => $account['country'],
                    /* False while Stripe is still reviewing the account: nothing to set up yet */
                    'card_payments_active' => $account['card_payments_active'],
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'enabled' => (bool) config('ninja.terminal_enabled'),
            'plan_ok' => !(Ninja::isHosted() && $user->account->isFreeHostedClient()),
            'gateways' => $gateways,
            'setup_gateways' => $setup_gateways,
            'company_country' => $company_country,
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

    public function quote(QuoteTerminalPaymentRequest $request, Invoice $invoice)
    {
        return response()->json(
            $request->companyGateway()->driver($invoice->client)->cardPresent()->quote(floatval($request->input('amount')))
        );
    }

    public function storePaymentIntent(StoreTerminalPaymentIntentRequest $request, Invoice $invoice)
    {
        return response()->json(
            $request->companyGateway()->driver($invoice->client)->cardPresent()->createPaymentIntent(auth()->user(), $invoice, [
                'amount' => floatval($request->input('amount')),
                'collection_id' => $request->input('collection_id'),
            ])
        );
    }

    public function finalize(FinalizeTerminalPaymentIntentRequest $request, Invoice $invoice)
    {
        return response()->json(CardPresent::forCollection($request->paymentHash(), $invoice->client)->finalize($invoice));
    }

    public function complete(ActionTerminalPaymentIntentRequest $request, Invoice $invoice)
    {
        return response()->json(CardPresent::forCollection($request->paymentHash(), $invoice->client)->complete($invoice));
    }

    public function cancel(ActionTerminalPaymentIntentRequest $request, Invoice $invoice)
    {
        return response()->json(CardPresent::forCollection($request->paymentHash(), $invoice->client)->cancel($invoice));
    }

    public function receipt(SendTerminalReceiptRequest $request, Invoice $invoice)
    {
        CardPresent::forCollection($request->paymentHash(), $invoice->client)->receipt($request->input('email'));

        return response()->json(['message' => ctrans('texts.emailed_payment')]);
    }
}
