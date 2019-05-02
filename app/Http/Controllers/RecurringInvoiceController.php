<?php

namespace App\Http\Controllers;

use App\Factory\CloneRecurringInvoiceFactory;
use App\Factory\CloneRecurringInvoiceToQuoteFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Filters\RecurringInvoiceFilters;
use App\Http\Requests\RecurringInvoice\ActionRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\CreateRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\DestroyRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\EditRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\ShowRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\StoreRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\UpdateRecurringInvoiceRequest;
use App\Jobs\Entity\ActionEntity;
use App\Models\RecurringInvoice;
use App\Repositories\BaseRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Transformers\RecurringInvoiceTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class RecurringInvoiceController
 * @package App\Http\Controllers\RecurringInvoiceController
 */

class RecurringInvoiceController extends BaseController
{

    use MakesHash;

    protected $entity_type = RecurringInvoice::class;

    protected $entity_transformer = RecurringInvoiceTransformer::class;

    /**
     * @var RecurringInvoiceRepository
     */
    protected $recurring_invoice_repo;

    protected $base_repo;

    /**
     * RecurringInvoiceController constructor.
     *
     * @param      \App\Repositories\RecurringInvoiceRepository  $recurring_invoice_repo  The RecurringInvoice repo
     */
    public function __construct(RecurringInvoiceRepository $recurring_invoice_repo)
    {

        parent::__construct();

        $this->recurring_invoice_repo = $recurring_invoice_repo;

    }

    /**
     * Show the list of recurring_invoices
     *
     * @param      \App\Filters\RecurringInvoiceFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
     */
    public function index(RecurringInvoiceFilters $filters)
    {
        
        $recurring_invoices = RecurringInvoice::filter($filters);
      
        return $this->listResponse($recurring_invoices);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @param      \App\Http\Requests\RecurringInvoice\CreateRecurringInvoiceRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function create(CreateRecurringInvoiceRequest $request)
    {

        $recurring_invoice = RecurringInvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($recurring_invoice);

    }


    /**
     * Store a newly created resource in storage.
     *
     * @param      \App\Http\Requests\RecurringInvoice\StoreRecurringInvoiceRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRecurringInvoiceRequest $request)
    {
        
        $recurring_invoice = $this->RecurringInvoice_repo->save($request, RecurringInvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id));

        return $this->itemResponse($recurring_invoice);

    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Http\Requests\RecurringInvoice\ShowRecurringInvoiceRequest  $request  The request
     * @param      \App\Models\RecurringInvoice                            $recurring_invoice  The RecurringInvoice
     *
     * @return \Illuminate\Http\Response
     */
    public function show(ShowRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {

        return $this->itemResponse($recurring_invoice);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param      \App\Http\Requests\RecurringInvoice\EditRecurringInvoiceRequest  $request  The request
     * @param      \App\Models\RecurringInvoice                            $recurring_invoice  The RecurringInvoice
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(EditRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {

        return $this->itemResponse($recurring_invoice);

    }
    
    /**
     * Update the specified resource in storage.
     *
     * @param      \App\Http\Requests\RecurringInvoice\UpdateRecurringInvoiceRequest  $request  The request
     * @param      \App\Models\RecurringInvoice                              $recurring_invoice  The RecurringInvoice
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {

        $recurring_invoice = $this->RecurringInvoice_repo->save(request(), $recurring_invoice);

        return $this->itemResponse($recurring_invoice);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\RecurringInvoice\DestroyRecurringInvoiceRequest  $request  
     * @param      \App\Models\RecurringInvoice                               $recurring_invoice  
     *
     * @return     \Illuminate\Http\Response
     */
    public function destroy(DestroyRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {

        $recurring_invoice->delete();

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

        $recurring_invoices = RecurringInvoice::withTrashed()->find($ids);

        $recurring_invoices->each(function ($recurring_invoice, $key) use($action){

            if(auth()->user()->can('edit', $recurring_invoice))
                $this->RecurringInvoice_repo->{$action}($recurring_invoice);

        });

        //todo need to return the updated dataset
        return $this->listResponse(RecurringInvoice::withTrashed()->whereIn('id', $ids));
        
    }

    public function action(ActionRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice, $action)
    {
        
        switch ($action) {
            case 'clone_to_RecurringInvoice':
          //      $recurring_invoice = CloneRecurringInvoiceFactory::create($recurring_invoice, auth()->user()->id);
          //      return $this->itemResponse($recurring_invoice);
                break;
            case 'clone_to_quote':
            //    $quote = CloneRecurringInvoiceToQuoteFactory::create($recurring_invoice, auth()->user()->id);
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
