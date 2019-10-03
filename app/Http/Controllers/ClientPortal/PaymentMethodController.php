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
use App\Utils\Traits\MakesDates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

class PaymentMethodController extends Controller
{
    use MakesDates;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Builder $builder)
    {
        $payment_methods = ClientGatewayToken::whereClientId(auth()->user()->client->id);
        $payment_methods->with('gateway_type');

        if (request()->ajax()) {

            return DataTables::of($payment_methods)->addColumn('action', function ($payment_method) {
                    return '<a href="/client/payment_methods/'. $payment_method->hashed_id .'" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';
                })
                ->editColumn('gateway_type_id', function ($payment_method){
                    return ctrans("texts.{$payment_method->gateway_type->alias}");
                })->editColumn('created_at', function ($payment_method){
                    return $this->formatDateTimestamp($payment_method->created_at, auth()->user()->client->date_format());
                })->editColumn('is_default', function ($payment_method){
                    return $payment_method->is_default ? ctrans('texts.default') : '';
                })->editColumn('meta', function ($payment_method) {
                    if(isset($payment_method->meta->exp_month) && isset($payment_method->meta->exp_year))
                        return "{$payment_method->meta->exp_month}/{$payment_method->meta->exp_year}";
                    else
                        return "";
                })->addColumn('last4', function ($payment_method) {
                    if(isset($payment_method->meta->last4))
                        return $payment_method->meta->last4;
                    else
                        return "";
                })->addColumn('brand', function ($payment_method) {
                    if(isset($payment_method->meta->brand))
                        return $payment_method->meta->brand;
                    else
                        return "";
                })
                ->rawColumns(['action', 'status_id','last4','brand'])
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
