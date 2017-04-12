<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Ninja\Repositories\InvoiceRepository;
use Response;

class QuoteApiController extends InvoiceApiController
{
    protected $invoiceRepo;

    protected $entityType = ENTITY_INVOICE;

    /**
     * @SWG\Get(
     *   path="/quotes",
     *   summary="List quotes",
     *   operationId="listQuotes",
     *   tags={"quote"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of quotes",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
     public function index()
     {
         $invoices = Invoice::scope()
                         ->withTrashed()
                         ->quotes()
                         ->with('invoice_items', 'client')
                         ->orderBy('created_at', 'desc');

         return $this->listResponse($invoices);
     }
}
