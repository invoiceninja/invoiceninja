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
        //REFACTOR - Here the request will contain an array of invoices and the amount to be charged for the invoice
        //REFACTOR - At this point, we will also need to modify the invoice to include a line item for a gateway fee if applicable
        //           This is tagged with a type_id of 3 which is for a pending gateway fee.
        //REFACTOR - In order to preserve state we should save the array of invoices and amounts and store it in db/cache and use a HASH
        //           to rehydrate these values in the payment response.
        //  [
        //   'invoices' => 
        //   [
        //     'invoice_id' => 'xx',
        //     'amount' => 'yy',
        //   ]             
        // ]
        
                            //old
                            // $invoices = Invoice::whereIn('id', $this->transformKeys(request()->invoices))
                            //     ->where('company_id', auth('contact')->user()->company->id)
                            //     ->get();

        /*find invoices*/
        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column(request()->invoices, 'invoice_id')))->get();

                            //old
                            // $amount = $invoices->sum('balance');

        /*filter only payable invoices*/
        $invoices = $invoices->filter(function ($invoice) {
            return $invoice->isPayable();
        });


        /*return early if no invoices*/
        if ($invoices->count() == 0) {
            return redirect()
                ->route('client.invoices.index')
                ->with(['warning' => 'No payable invoices selected.']);
        }

        /*iterate through invoices and add gateway fees*/
        foreach(request()->invoices as $payable_invoice)
        {
            $invoice = $invoices->first(function ($inv) use($payable_invoice) {
                            return $payable_invoice['invoice_id'] == $inv->hashed_id;
                        });

            if($invoice)
                $invoice->service()->addGatewayFee($payable_invoice['amount']);
        }

        /*Format invoices*/
        $invoices->fresh()->map(function ($invoice) {
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
            ->setPaymentMethod($payment_method_id)
            ->processPaymentView($data);
    }

    public function response(Request $request)
    {
        $gateway = CompanyGateway::find($request->input('company_gateway_id'));

        //REFACTOR - Entry point for the gateway response - we don't need to do anything at this point.
        //
        // - Inside each gateway driver, we should use have a generic code path (in BaseDriver.php)for successful/failed payment
        // 
        //   Success workflow
        //   
        // - Rehydrate the hash and iterate through the invoices and update the balances
        // - Update the type_id of the gateway fee to type_id 4
        // - Link invoices to payment
        // 
        //   Failure workflow
        // 
        // - Rehydrate hash, iterate through invoices and remove type_id 3's
        // - Recalcuate invoice totals
        
        return $gateway
            ->driver(auth()->user()->client)
            ->setPaymentMethod($request->input('payment_method_id'))
            ->processPaymentResponse($request);
    }
}
