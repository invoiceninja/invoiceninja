<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Filters\PaymentFilters;
use App\Http\Controllers\Controller;
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
use Yajra\DataTables\Html\Builder;

/**
 * Class PaymentController
 * @package App\Http\Controllers\ClientPortal\PaymentController
 */

class PaymentController extends Controller
{

    use MakesHash;
    use MakesDates;

    /**
     * Show the list of Invoices
     *
     * @param      \App\Filters\InvoiceFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     */
    public function index(PaymentFilters $filters, Builder $builder)
    {
        //$payments = Payment::filter($filters);
        $payments = Payment::with('type','client');

        if (request()->ajax()) {

            return DataTables::of($payments)->addColumn('action', function ($payment) {
                    return '<a href="/client/payments/'. $payment->hashed_id .'" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';
                })->editColumn('type_id', function ($payment) {
                    return $payment->type->name;
                })
                ->editColumn('status_id', function ($payment){
                    return Payment::badgeForStatus($payment->status_id);
                })
                ->editColumn('date', function ($payment){
                    //return $payment->date;
                    return $payment->formatDate($payment->date, $payment->client->date_format());
                })
                ->editColumn('amount', function ($payment) {
                    return Number::formatMoney($payment->amount, $payment->client);
                })
                ->rawColumns(['action', 'status_id','type_id'])
                ->make(true);
        
        }

        $data['html'] = $builder;
      
        return view('portal.default.payments.index', $data);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Models\Invoice $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Payment $payment)
    {
        $payment->load('invoices');

        $data['payment'] = $payment;

        return view('portal.default.payments.show', $data);

    }

    /**
     * Presents the payment screen for a given
     * gateway and payment method.
     * The request will also contain the amount
     * and invoice ids for reference.
     * 
     * @return void                     
     */
    public function process()
    {

        $invoices = Invoice::whereIn('id', $this->transformKeys(explode(",",request()->input('hashed_ids'))))
                                ->whereClientId(auth()->user()->client->id)
                                ->get();

        $amount = $invoices->sum('balance');     

        $invoices = $invoices->filter(function ($invoice){
            return $invoice->isPayable();
        });

        if($invoices->count() == 0)
            return back()->with(['warning' => 'No payable invoices selected']);
        
        $invoices->map(function ($invoice){
            $invoice->balance = Number::formatMoney($invoice->balance, $invoice->client);
            $invoice->due_date = $this->formatDate($invoice->due_date, $invoice->client->date_format());
            return $invoice;
        });

        $payment_methods = auth()->user()->client->getPaymentMethods($amount);

        //boot the payment gateway
        $gateway = CompanyGateway::find(request()->input('company_gateway_id'));

        $payment_method_id = request()->input('payment_method_id');

        //if there is a gateway fee, now is the time to calculate it 
        //and add it to the invoice
        
        $data = [
            'invoices' => $invoices,
            'amount' => $amount,
            'fee' => $gateway->calcGatewayFee($amount),
            'amount_with_fee' => $amount + $gateway->calcGatewayFee($amount),
            'token' => auth()->user()->client->gateway_token($gateway->id, $payment_method_id),
            'payment_method_id' => $payment_method_id,
            'hashed_ids' => explode(",",request()->input('hashed_ids')),
        ];
        
        
        return $gateway->driver(auth()->user()->client)->processPaymentView($data);

    }

    public function response(Request $request)
    {
        $gateway = CompanyGateway::find($request->input('company_gateway_id'));

        return $gateway->driver(auth()->user()->client)->processPaymentResponse($request);

    }

}
