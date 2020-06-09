<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Filters\PaymentFilters;
use App\Http\Controllers\Controller;
use App\Jobs\Invoice\InjectSignature;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

/**
 * Class PaymentController
 * @package App\Http\Controllers\ClientPortal\PaymentController
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
        $invoices = Invoice::whereIn('id', $this->transformKeys(request()->invoices))
            ->where('company_id', auth('contact')->user()->company->id)
            ->get();

        $amount = $invoices->sum('balance');

        $invoices = $invoices->filter(function ($invoice) {
            return $invoice->isPayable();
        });

        if ($invoices->count() == 0) {
            return redirect()
                ->route('client.invoices.index')
                ->with(['warning' => 'No payable invoices selected.']);
        }

        $invoices->map(function ($invoice) {
            $invoice->balance = Number::formatMoney($invoice->balance, $invoice->client);
            $invoice->due_date = $this->formatDate($invoice->due_date, $invoice->client->date_format());
            
            return $invoice;
        });

        if ((bool) request()->signature) {
            $invoices->each(function ($invoice) {
                InjectSignature::dispatch($invoice, request()->signature);
            });
        }

        $payment_methods = auth()->user()->client->getPaymentMethods($amount);
        $gateway = CompanyGateway::find(request()->input('company_gateway_id'));

        $payment_method_id = request()->input('payment_method_id');

        // Place to calculate gateway fee.

        $data = [
            'invoices' => $invoices,
            'amount' => $amount,
            'fee' => $gateway->calcGatewayFee($amount),
            'amount_with_fee' => $amount + $gateway->calcGatewayFee($amount),
            'token' => auth()->user()->client->gateway_token($gateway->id, $payment_method_id),
            'payment_method_id' => $payment_method_id,
            'hashed_ids' => request()->invoices,
        ];

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod('App\\PaymentDrivers\\Stripe\\SOFORT')
            ->processPaymentView($data);
    }

    public function response(Request $request)
    {
        $gateway = CompanyGateway::find($request->input('company_gateway_id'));

        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod('App\\PaymentDrivers\\Stripe\\SOFORT')
            ->processPaymentResponse($request);
    }
}
