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
use App\Jobs\Entity\ActionEntity;
use App\Models\Invoice;
use App\Repositories\BaseRepository;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Yajra\DataTables\Html\Builder;

/**
 * Class InvoiceController
 * @package App\Http\Controllers\ClientPortal\InvoiceController
 */

class InvoiceController extends Controller
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
    {//
        $invoices = Invoice::filter($filters);

        if (request()->ajax()) {

            return DataTables::of(Invoice::filter($filters))->addColumn('action', function ($invoice) {
                    return '<a href="/client/invoices/'. $invoice->hashed_id .'/edit" class="btn btn-xs btn-primary"><i class="glyphicon glyphicon-edit"></i>'.ctrans('texts.view').'</a>';
                })
                ->addColumn('checkbox', function ($invoice){
                    return '<input type="checkbox" name="hashed_ids[]" value="'. $invoice->hashed_id .'"/>';
                })
                ->editColumn('status_id', function ($invoice){
                    return Invoice::badgeForStatus($invoice->status);
                })
                ->rawColumns(['checkbox', 'action', 'status_id'])
                ->make(true);
        
        }

        $data['html'] = $builder;
      
        return view('portal.default.invoices.index', $data);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Models\Invoice $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Invoice $invoice)
    {


    }

    /**
     * Pay one or more invoices
     * 
     * @return View
     */
    public function bulk()
    {
Log::error(request()->input('hashed_ids'));

        $transformed_ids = $this->transformKeys(explode(",",request()->input('hashed_ids')));

        $invoices = Invoice::whereIn('id', $transformed_ids)
                            ->whereClientId(auth()->user()->client->id)
                            ->get()
                            ->filter(function ($invoice){
                                return $invoice->isPayable();
                            });

Log::error($invoices);

        $data = [
            'invoices' => $invoices,
        ];

        return view('portal.default.invoices.payment', $data);
                
    }


    
}
