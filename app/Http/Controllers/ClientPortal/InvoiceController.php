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
                    Log::error($invoice->status);
                    return Invoice::badgeForStatus($invoice->status);
                })
                ->rawColumns(['checkbox', 'action', 'status_id'])
                ->make(true);
        
        }

        $builder->addAction();
        $builder->addCheckbox();
        
        /**todo this is redundant, but keep in case we want to build this serverside*/
        $html = $builder->columns([
            ['data' => 'checkbox', 'name' => 'checkbox', 'title' => '', 'searchable' => false, 'orderable' => false],
            ['data' => 'invoice_number', 'name' => 'invoice_number', 'title' => trans('texts.invoice_number'), 'visible'=> true],
            ['data' => 'invoice_date', 'name' => 'invoice_date', 'title' => trans('texts.invoice_date'), 'visible'=> true],
            ['data' => 'amount', 'name' => 'amount', 'title' => trans('texts.total'), 'visible'=> true],
            ['data' => 'balance', 'name' => 'balance', 'title' => trans('texts.balance'), 'visible'=> true],
            ['data' => 'due_date', 'name' => 'due_date', 'title' => trans('texts.due_date'), 'visible'=> true],
            ['data' => 'status', 'name' => 'status', 'title' => trans('texts.status'), 'visible'=> true],
            ['data' => 'action', 'name' => 'action', 'title' => '', 'searchable' => false, 'orderable' => false],
        ]);

        $builder->ajax([
            'url' => route('client.invoices.index'),
            'type' => 'GET',
            'data' => 'function(d) { d.key = "value"; }',
        ]);

        $data['html'] = $html;
      
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
     * Perform bulk actions on the list view
     * 
     * @return Collection
     */
    public function bulk()
    {

        
    }


    
}
