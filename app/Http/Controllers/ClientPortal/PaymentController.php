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
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

/**
 * Class InvoiceController
 * @package App\Http\Controllers\ClientPortal\InvoiceController
 */

class PaymentController extends Controller
{

    use MakesHash;

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
        $payments = Payment::all();
        if (request()->ajax()) {

            return DataTables::of($payments)->addColumn('action', function ($payment) {
                    return '<a href="/client/payments/'. $payment->hashed_id .'/edit" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';
                })->editColumn('payment_type_id', function ($payment) {
                    return $payment->type->name;
                })
                ->editColumn('status_id', function ($payment){
                    return Payment::badgeForStatus($payment->status_id);
                })
                ->rawColumns(['action', 'status_id','payment_type_id'])
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
    public function show(RecurringInvoice $invoice)
    {


    }



}
