<?php

namespace App\Http\Controllers;

use App\Factory\CloneInvoiceFactory;
use App\Factory\CloneInvoiceToQuoteFactory;
use App\Factory\InvoiceFactory;
use App\Filters\InvoiceFilters;
use App\Http\Requests\Invoice\ActionInvoiceRequest;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Invoice\DestroyInvoiceRequest;
use App\Http\Requests\Invoice\EditInvoiceRequest;
use App\Http\Requests\Invoice\ShowInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Jobs\Entity\ActionEntity;
use App\Models\Invoice;
use App\Repositories\BaseRepository;
use App\Repositories\InvoiceRepository;
use App\Transformers\InvoiceTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class InvoiceController
 * @package App\Http\Controllers\InvoiceController
 */

class InvoiceController extends BaseController
{

    use MakesHash;

    protected $entity_type = Invoice::class;

    protected $entity_transformer = InvoiceTransformer::class;

    /**
     * @var InvoiceRepository
     */
    protected $invoice_repo;

    protected $base_repo;

    /**
     * InvoiceController constructor.
     *
     * @param      \App\Repositories\InvoiceRepository  $invoice_repo  The invoice repo
     */
    public function __construct(InvoiceRepository $invoice_repo)
    {

        parent::__construct();

        $this->invoice_repo = $invoice_repo;

    }

    /**
     * Show the list of Invoices
     *
     * @param      \App\Filters\InvoiceFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     */
    public function index(InvoiceFilters $filters)
    {
        
        $invoices = Invoice::filter($filters);
      
        return $this->listResponse($invoices);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @param      \App\Http\Requests\Invoice\CreateInvoiceRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateInvoiceRequest $request)
    {

        $invoice = InvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($invoice);

    }


    /**
     * Store a newly created resource in storage.
     *
     * @param      \App\Http\Requests\Invoice\StoreInvoiceRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreInvoiceRequest $request)
    {
        
        $invoice = $this->invoice_repo->save($request, InvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id));

        return $this->itemResponse($invoice);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Http\Requests\Invoice\ShowInvoiceRequest  $request  The request
     * @param      \App\Models\Invoice                            $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ShowInvoiceRequest $request, Invoice $invoice)
    {

        return $this->itemResponse($invoice);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param      \App\Http\Requests\Invoice\EditInvoiceRequest  $request  The request
     * @param      \App\Models\Invoice                            $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(EditInvoiceRequest $request, Invoice $invoice)
    {

        return $this->itemResponse($invoice);

    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param      \App\Http\Requests\Invoice\UpdateInvoiceRequest  $request  The request
     * @param      \App\Models\Invoice                              $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {

        $invoice = $this->invoice_repo->save(request(), $invoice);

        return $this->itemResponse($invoice);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\Invoice\DestroyInvoiceRequest  $request  
     * @param      \App\Models\Invoice                               $invoice  
     *
     * @return     \Illuminate\Http\Response
     */
    public function destroy(DestroyInvoiceRequest $request, Invoice $invoice)
    {

        $invoice->delete();

        return response()->json([], 200);

    }

    /**
     * Perform bulk actions on the list view
     * 
     * @return Collection
     */
    public function bulk()
    {

        $action = request()->input('action');
        
        $ids = request()->input('ids');

        $invoices = Invoice::withTrashed()->find($ids);

        $invoices->each(function ($invoice, $key) use($action){

            if(auth()->user()->can('edit', $invoice))
                $this->invoice_repo->{$action}($invoice);

        });

        //todo need to return the updated dataset
        return $this->listResponse(Invoice::withTrashed()->whereIn('id', $ids));
        
    }

    public function action(ActionInvoiceRequest $request, Invoice $invoice, $action)
    {
        
        switch ($action) {
            case 'clone_to_invoice':
                $invoice = CloneInvoiceFactory::create($invoice, auth()->user()->id);
                return $this->itemResponse($invoice);
                break;
            case 'clone_to_quote':
                $quote = CloneInvoiceToQuoteFactory::create($invoice, auth()->user()->id);
                // todo build the quote transformer and return response here 
                break;
            case 'history':
                # code...
                break;
            case 'delivery_note':
                # code...
                break;
            case 'mark_paid':
                # code...
                break;
            case 'archive':
                # code...
                break;
            case 'delete':
                # code...
                break;
            case 'email':
                //dispatch email to queue
                break;

            default:
                # code...
                break;
        }
    }
    
}
