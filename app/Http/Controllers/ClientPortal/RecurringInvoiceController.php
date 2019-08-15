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

use App\Filters\InvoiceFilters;
use App\Http\Controllers\Controller;
use App\Models\RecurringInvoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

/**
 * Class InvoiceController
 * @package App\Http\Controllers\ClientPortal\InvoiceController
 */

class RecurringInvoiceController extends Controller
{

    use MakesHash;

    /**
     * Show the list of Invoices
     *
     * @param      \App\Filters\InvoiceFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     */
    public function index(InvoiceFilters $filters, Builder $builder)
    {
        $invoices = Invoice::filter($filters);

        if (request()->ajax()) {

            return DataTables::of($invoices)->addColumn('action', function ($invoice) {
                    return '<a href="/client/recurring_invoices/'. $invoice->hashed_id .'/edit" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';
                })
                ->editColumn('status_id', function ($invoice){
                    return Invoice::badgeForStatus($invoice->status);
                })
                ->rawColumns(['checkbox', 'action', 'status_id'])
                ->make(true);
        
        }

        $data['html'] = $builder;
      
        return view('portal.default.recurring_invoices.index', $data);

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
