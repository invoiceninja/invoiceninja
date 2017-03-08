<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Ninja\Repositories\InvoiceRepository;
use Response;

class QuoteApiController extends InvoiceAPIController
{
    protected $invoiceRepo;

    protected $entityType = ENTITY_INVOICE;

    public function __construct(InvoiceRepository $invoiceRepo)
    {
        parent::__construct();

        $this->invoiceRepo = $invoiceRepo;
    }

    /**
     * @SWG\Get(
     *   path="/quotes",
     *   tags={"quote"},
     *   summary="List of quotes",
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

    /**
     * @SWG\Get(
     *   path="/quotes/{quote_id}",
     *   summary="Individual Quote",
     *   tags={"quote"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="quote_id",
     *     type="integer",
     *     required="true"
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single quote",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    /**
     * @SWG\Post(
     *   path="/quotes",
     *   tags={"quote"},
     *   summary="Create a quote",
     *   @SWG\Parameter(
     *     in="body",
     *     name="quote",
     *     @SWG\Schema(ref="#/definitions/Invoice")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New quote",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    /**
     * @SWG\Put(
     *   path="/quotes/{quote_id}",
     *   tags={"quote"},
     *   summary="Update a quote",
     *   @SWG\Parameter(
     *     in="path",
     *     name="quote_id",
     *     type="integer",
     *     required="true"
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="quote",
     *     @SWG\Schema(ref="#/definitions/Invoice")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated quote",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    /**
     * @SWG\Delete(
     *   path="/quotes/{quote_id}",
     *   tags={"quote"},
     *   summary="Delete a quote",
     *   @SWG\Parameter(
     *     in="path",
     *     name="quote_id",
     *     type="integer",
     *     required="true"
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted quote",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
}
