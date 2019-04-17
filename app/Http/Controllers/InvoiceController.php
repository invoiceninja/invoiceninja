<?php

namespace App\Http\Controllers;

use App\Factory\InvoiceFactory;
use App\Filters\InvoiceFilters;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Invoice\EditInvoiceRequest;
use App\Http\Requests\Invoice\ShowInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Transformers\InvoiceTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

/**
 * Class ClientController
 * @package App\Http\Controllers
 * @covers App\Http\Controllers\ClientController
 */

class InvoiceController extends BaseController
{

    use MakesHash;

    protected $entity_type = Invoice::class;

    protected $entity_transformer = InvoiceTransformer::class;

    /**
     * @var ClientRepository
     */
    protected $invoice_repo;

    /**
     * ClientController constructor.
     * @param ClientRepository $clientRepo
     */
    public function __construct(InvoiceRepository $invoice_repo)
    {
        parent::__construct();

        $this->invoice_repo = $invoice_repo;

    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function index(InvoiceFilters $filters)
    {
        
        $invoices = Invoice::filter($filters);
      
        return $this->listResponse($invoices);

    }

    /**
     * Show the form for creating a new resource.
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
     * @param  \Illuminate\Http\Request  $request
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ShowInvoiceRequest $request, Invoice $invoice)
    {

        return $this->itemResponse($invoice);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(EditInvoiceRequest $request, Invoice $invoice)
    {

        return $this->itemResponse($invoice);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {

        $invoice = $this->invoice_repo->save($request, $invoice);

        return $this->itemResponse($invoice);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(DestroyInvoiceRequest $request, Invoice $invoice)
    {

        $invoice->delete();

        return response()->json([], 200);

    }
}
