<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Exceptions\PaymentFailed;
use App\Factory\PaymentFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Invoice\InjectSignature;
use App\Jobs\Util\SystemLogger;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\Services\ClientPortal\InstantPayment;
use App\Services\Subscription\SubscriptionService;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Class PaymentController.
 */
class PaymentController extends Controller
{
    use MakesHash;
    use MakesDates;

    /**
     * Show the list of payments.
     *
     * @return Factory|View
     */
    public function index()
    {
        return $this->render('payments.index');
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param Payment $payment
     * @return Factory|View
     */
    public function show(Request $request, Payment $payment)
    {
        $payment->load('invoices');

        return $this->render('payments.show', [
            'payment' => $payment,
        ]);
    }

    public function catch_process(Request $request)
    {
        return $this->render('payments.index');
    }

    /**
     * Presents the payment screen for a given
     * gateway and payment method.
     * The request will also contain the amount
     * and invoice ids for reference.
     *
     * @param Request $request
     * @return RedirectResponse|mixed
     */
    public function process(Request $request)
    {
        return (new InstantPayment($request))->run();
    }

    public function response(PaymentResponseRequest $request)
    {

        $gateway = CompanyGateway::findOrFail($request->input('company_gateway_id'));
        $payment_hash = PaymentHash::where('hash', $request->payment_hash)->firstOrFail();
        $invoice = Invoice::with('client')->find($payment_hash->fee_invoice_id);
        $client = $invoice ? $invoice->client : auth()->guard('contact')->user()->client;

        // 09-07-2022 catch duplicate responses for invoices that already paid here.
        if($invoice && $invoice->status_id == Invoice::STATUS_PAID){

            $data = [
                'invoice' => $invoice,
                'key' => false
            ];

            if ($request->query('mode') === 'fullscreen') {
                return render('invoices.show-fullscreen', $data);
            }

            return $this->render('invoices.show', $data);

        }

            return $gateway
                ->driver($client)
                ->setPaymentMethod($request->input('payment_method_id'))
                ->setPaymentHash($payment_hash)
                ->checkRequirements()
                ->processPaymentResponse($request);
    }

    /**
     * Pay for invoice/s using credits only.
     *
     * @param Request $request The request object
     * @return Response         The response view
     */
    public function credit_response(Request $request)
    {
        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->first();

        /* Hydrate the $payment */
        if ($payment_hash->payment()->exists()) {
            $payment = $payment_hash->payment;
        } else {
            $payment = PaymentFactory::create($payment_hash->fee_invoice->company_id, $payment_hash->fee_invoice->user_id);
            $payment->client_id = $payment_hash->fee_invoice->client_id;

            $payment->saveQuietly();
            $payment->currency_id = $payment->client->getSetting('currency_id');
            $payment->saveQuietly();

            $payment_hash->payment_id = $payment->id;
            $payment_hash->save();
        }

        $payment = $payment->service()->applyCredits($payment_hash)->save();

        event('eloquent.created: App\Models\Payment', $payment);

        if (property_exists($payment_hash->data, 'billing_context')) {
            $billing_subscription = \App\Models\Subscription::find($payment_hash->data->billing_context->subscription_id);

            return (new SubscriptionService($billing_subscription))->completePurchase($payment_hash);
        }

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    public function processCreditPayment(Request $request, array $data)
    {
        return render('gateways.credit.index', $data);
    }
}
