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

namespace App\Http\Controllers\ClientPortal;

use App\Factory\PaymentFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Invoice\InjectSignature;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
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
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(Request $request, Payment $payment)
    {
        $payment->load('invoices');

        return $this->render('payments.show', [
            'payment' => $payment,
        ]);
    }

    /**
     * Presents the payment screen for a given
     * gateway and payment method.
     * The request will also contain the amount
     * and invoice ids for reference.
     *
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function process()
    {
        $gateway = CompanyGateway::findOrFail(request()->input('company_gateway_id'));

        /**
         * find invoices
         *
         * ['invoice_id' => xxx, 'amount' => 22.00]
         * 
         */

        $payable_invoices = collect(request()->payable_invoices);
        $invoices = Invoice::whereIn('id', $this->transformKeys($payable_invoices->pluck('invoice_id')->toArray()))->get();

        /* pop non payable invoice from the $payable_invoices array */
        $payable_invoices = $payable_invoices->filter(function ($payable_invoice) use ($invoices){

            return $invoices->where('hashed_id', $payable_invoice['invoice_id'])->first()->isPayable();

        });

        /*return early if no invoices*/
        if ($payable_invoices->count() == 0) {
            return redirect()
                ->route('client.invoices.index')
                ->with(['warning' => 'No payable invoices selected.']);
        }

        $settings = auth()->user()->client->getMergedSettings();

        /*iterate through invoices and add gateway fees and other payment metadata*/
        $payable_invoices = $payable_invoices->map(function($payable_invoice) use($invoices, $settings){
        
            $payable_invoice['amount'] = Number::parseFloat($payable_invoice['amount']);

            $invoice = $invoices->first(function ($inv) use ($payable_invoice) {
                return $payable_invoice['invoice_id'] == $inv->hashed_id;
            });

            // Check if company supports over & under payments.
            // In case it doesn't this is where process should stop.

            $payable_amount = Number::roundValue(Number::parseFloat($payable_invoice['amount']), auth()->user()->client->currency()->precision);
            $invoice_balance = Number::roundValue($invoice->balance, auth()->user()->client->currency()->precision);

            if ($settings->client_portal_allow_under_payment == false && $settings->client_portal_allow_over_payment == false) {
                $payable_invoice['amount'] = Number::roundValue(($invoice->partial > 0 ? $invoice->partial : $invoice->balance), auth()->user()->client->currency()->precision);
            } // We don't allow either of these, reset the amount to default invoice (to prevent inspect element payments).

            if ($settings->client_portal_allow_under_payment) {
                if ($payable_invoice['amount'] < $settings->client_portal_under_payment_minimum) {
                    return redirect()
                        ->route('client.invoices.index')
                        ->with('message', ctrans('texts.minimum_required_payment', ['amount' => $settings->client_portal_under_payment_minimum]));
                }
            } else {
                $payable_amount = Number::roundValue(Number::parseFloat($payable_invoice['amount']), auth()->user()->client->currency()->precision);
                $invoice_balance = Number::roundValue($invoice->balance, auth()->user()->client->currency()->precision);

                if ($payable_amount < $invoice_balance) {
                    return redirect()
                        ->route('client.invoices.index')
                        ->with('message', ctrans('texts.under_payments_disabled'));
                }
            } // Make sure 'amount' from form is not lower than 'amount' from invoice.

            if ($settings->client_portal_allow_over_payment == false) {
                if ($payable_amount > $invoice_balance) {
                    return redirect()
                        ->route('client.invoices.index')
                        ->with('message', ctrans('texts.over_payments_disabled'));
                }
            } // Make sure 'amount' from form is not higher than 'amount' from invoice.

            $payable_invoice['due_date'] = $this->formatDate($invoice->due_date, $invoice->client->date_format());
            $payable_invoice['invoice_number'] = $invoice->number;

            if (isset($invoice->po_number)) {
                $additional_info = $invoice->po_number;
            } elseif (isset($invoice->public_notes)) {
                $additional_info = $invoice->public_notes;
            } else {
                $additional_info = $invoice->date;
            }

            $payable_invoice['additional_info'] = $additional_info;

            return $payable_invoice;

        });

        if ((bool) request()->signature) {
            $invoices->each(function ($invoice) {
                InjectSignature::dispatch($invoice, request()->signature);
            });
        }

        $payment_method_id = request()->input('payment_method_id');

        $invoice_totals = $payable_invoices->sum('amount');

        $first_invoice = $invoices->first();

        $credit_totals = $first_invoice->client->service()->getCreditBalance();

        $starting_invoice_amount = $first_invoice->amount;

        $first_invoice->service()->addGatewayFee($gateway, $payment_method_id, $invoice_totals)->save();

        /**
         *
         * The best way to determine the exact gateway fee is to not calculate it in isolation (due to rounding)
         * but to simply add it as a line item, and then subtract the starting and finishing amounts of
         * the invoice.
         */
        $fee_totals = $first_invoice->amount - $starting_invoice_amount;

        $payment_hash = new PaymentHash;
        $payment_hash->hash = Str::random(128);
        $payment_hash->data = $payable_invoices->toArray();
        $payment_hash->fee_total = $fee_totals;
        $payment_hash->fee_invoice_id = $first_invoice->id;
        $payment_hash->save();

        $totals = [
            'credit_totals' => $credit_totals,
            'invoice_totals' => $invoice_totals,
            'fee_total' => $fee_totals,
            'amount_with_fee' => max(0, (($invoice_totals + $fee_totals) - $credit_totals)),
        ];

        $data = [
            'payment_hash' => $payment_hash->hash,
            'total' => $totals,
            'invoices' => $payable_invoices,
            'token' => auth()->user()->client->gateway_token($gateway->id, $payment_method_id),
            'payment_method_id' => $payment_method_id,
            'amount_with_fee' => $invoice_totals + $fee_totals,
        ];

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod($payment_method_id)
            ->processPaymentView($data);
    }

    public function response(PaymentResponseRequest $request)
    {
        /*Payment Gateway*/
        $gateway = CompanyGateway::find($request->input('company_gateway_id'))->firstOrFail();

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod($request->input('payment_method_id'))
            ->processPaymentResponse($request);
    }

    public function credit_response(Request $request)
    {
        $payment_hash = PaymentHash::find($request->input('payment_hash'));

        if($payment_hash->payment->exists())
            $payment = $payment_hash->payment;
        else {
            $payment = PaymentFactory::create($payment_hash->fee_invoice->company_id, $payment_hash->fee_invoice->user_id)->save();
            $payment_hash->payment_id = $payment->id;
            $payment_hash->save();
        }

        collect($payment_hash->invoices())->each(function ($payable_invoice) use ($payment, $payment_hash){

            $invoice = Invoice::find($this->decodePrimaryKey($payable_invoice['invoice_id']));
            $amount = $payable_invoice['amount'];

            $credits = $payment_hash->fee_invoice
                                    ->client
                                    ->service()
                                    ->getCredits();
                                    
                foreach($credits as $credit)
                {   
                    //starting invoice balance
                    $invoice_balance = $invoice->balance;

                    //credit payment applied
                    $invoice = $credit->service()->applyPayment($invoice, $amount, $payment);

                    //amount paid from invoice calculated
                    $remaining_balance = ($invoice_balance - $invoice->balance);

                    //reduce the amount to be paid on the invoice from the NEXT credit
                    $amount -= $remaining_balance;

                    //break if the invoice is no longer PAYABLE OR there is no more amount to be applied
                    if(!$invoice->isPayable() || (int)$amount == 0)
                        break;
                }

        });


    }
}
