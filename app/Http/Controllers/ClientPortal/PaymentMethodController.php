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

use App\Http\Controllers\Controller;
use App\Models\ClientGatewayToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class PaymentMethodController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $payment_methods = ClientGatewayToken::whereClientId(auth()->user()->client->id);

        if (request()->ajax()) {

            return DataTables::of($payment_methods)->addColumn('action', function ($invoice) {
                    return '<a href="/client/payment_methods/'. $payment_methods->hashed_id .'" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';
                })
                ->editColumn('status_id', function ($invoice){
                    return Invoice::badgeForStatus($invoice->status);
                })->editColumn('invoice_date', function ($invoice){
                    return $this->formatDate($invoice->invoice_date, $invoice->client->date_format());
                })->editColumn('due_date', function ($invoice){
                    return $this->formatDate($invoice->due_date, $invoice->client->date_format());
                })->editColumn('balance', function ($invoice) {
                    return Number::formatMoney($invoice->balance, $invoice->client);
                })->editColumn('amount', function ($invoice) {
                    return Number::formatMoney($invoice->amount, $invoice->client);
                })
                ->rawColumns(['action', 'status_id'])
                ->make(true);
        
        }

        $data['html'] = $builder;
      
        return view('portal.default.payment_methods.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $gateway = auth()->user()->client->getCreditCardGateway();

        $data = [
            'gateway' => $gateway,
            'gateway_type_id' => 1,
            'token' => false,
        ];

        return $gateway->driver(auth()->user()->client)->authorizeCreditCardView($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $gateway = auth()->user()->client->getCreditCardGateway();

        return $gateway->driver(auth()->user()->client)->authorizeCreditCardResponse($request);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy()
    {

    }
}
