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
    {
        
        if (request()->ajax()) {

            return DataTables::of(Invoice::all())
                ->make(true);
        }

        $builder->addAction();
        $builder->addCheckbox();
        
        $html = $builder->columns([
            ['data' => 'checkbox', 'name' => 'checkbox', 'title' => '', 'searchable' => false, 'orderable' => false],
            ['data' => 'invoice_number', 'name' => 'invoice_number', 'title' => trans('texts.invoice_number'), 'visible'=> true],
          //  ['data' => 'full_name', 'name' => 'full_name', 'title' => trans('texts.contact'), 'visible'=> true],
          //  ['data' => 'email', 'name' => 'email', 'title' => trans('texts.email'), 'visible'=> true],
          //  ['data' => 'created_at', 'name' => 'created_at', 'title' => trans('texts.date_created'), 'visible'=> true],
          //  ['data' => 'last_login', 'name' => 'last_login', 'title' => trans('texts.last_login'), 'visible'=> true],
           // ['data' => 'balance', 'name' => 'balance', 'title' => trans('texts.balance'), 'visible'=> true],
           // ['data' => 'action', 'name' => 'action', 'title' => '', 'searchable' => false, 'orderable' => false],
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
