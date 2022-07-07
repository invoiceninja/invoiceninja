<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
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
use App\Http\Requests\Invoice\UpdateReminderRequest;
use App\Http\Requests\Invoice\UploadInvoiceRequest;
use App\Jobs\Entity\EmailEntity;
use App\Jobs\Invoice\BulkInvoiceJob;
use App\Jobs\Invoice\StoreInvoice;
use App\Jobs\Invoice\UpdateReminders;
use App\Jobs\Invoice\ZipInvoices;
use App\Jobs\Ninja\TransactionLog;
use App\Jobs\Util\UnlinkFile;
use App\Models\Account;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\TransactionEvent;
use App\Repositories\InvoiceRepository;
use App\Transformers\InvoiceTransformer;
use App\Transformers\QuoteTransformer;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Class InvoiceController.
 */
class InvoiceController extends BaseController
{
    use MakesHash;
    use SavesDocuments;
    
    protected $entity_type = Invoice::class;

    protected $entity_transformer = InvoiceTransformer::class;

    /**
     * @var InvoiceRepository
     */
    protected $invoice_repo;

    /**
     * InvoiceController constructor.
     *
     * @param InvoiceRepository $invoice_repo  The invoice repo
     */
    public function __construct(InvoiceRepository $invoice_repo)
    {
        parent::__construct();

        $this->invoice_repo = $invoice_repo;
    }

    /**
     * Show the list of Invoices.
     *
     * @param InvoiceFilters $filters  The filters
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/invoices",
     *      operationId="getInvoices",
     *      tags={"invoices"},
     *      summary="Gets a list of invoices",
     *      description="Lists invoices, search and filters allow fine grained lists to be generated.
     *
     *		Query parameters can be added to performed more fine grained filtering of the invoices, these are handled by the InvoiceFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of invoices",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function index(InvoiceFilters $filters)
    {
        set_time_limit(45);

        $invoices = Invoice::filter($filters);

        return $this->listResponse($invoices);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateInvoiceRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/invoices/create",
     *      operationId="getInvoicesCreate",
     *      tags={"invoices"},
     *      summary="Gets a new blank invoice object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateInvoiceRequest $request)
    {
        $invoice = InvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($invoice);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreInvoiceRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/invoices",
     *      operationId="storeInvoice",
     *      tags={"invoices"},
     *      summary="Adds a invoice",
     *      description="Adds an invoice to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/FillableInvoice")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreInvoiceRequest $request)
    {

        $invoice = $this->invoice_repo->save($request->all(), InvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id));

        $invoice = $invoice->service()
                           ->fillDefaults()
                           ->triggeredActions($request)
                           ->adjustInventory()
                           ->save();

        event(new InvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        
        $transaction = [
            'invoice' => $invoice->transaction_event(),
            'payment' => [],
            'client' => $invoice->client->transaction_event(),
            'credit' => [],
            'metadata' => [],
        ];

        TransactionLog::dispatch(TransactionEvent::INVOICE_UPDATED, $transaction, $invoice->company->db);
        
        return $this->itemResponse($invoice);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowInvoiceRequest $request  The request
     * @param Invoice $invoice  The invoice
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/invoices/{id}",
     *      operationId="showInvoice",
     *      tags={"invoices"},
     *      summary="Shows an invoice",
     *      description="Displays an invoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowInvoiceRequest $request, Invoice $invoice)
    {
        return $this->itemResponse($invoice);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditInvoiceRequest $request  The request
     * @param Invoice $invoice  The invoice
     *
     * @return Response
     *
     * @OA\Get(
     *      path="/api/v1/invoices/{id}/edit",
     *      operationId="editInvoice",
     *      tags={"invoices"},
     *      summary="Shows an invoice for editting",
     *      description="Displays an invoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function edit(EditInvoiceRequest $request, Invoice $invoice)
    {
        return $this->itemResponse($invoice);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateInvoiceRequest $request  The request
     * @param Invoice $invoice  The invoice
     *
     * @return Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/invoices/{id}",
     *      operationId="updateInvoice",
     *      tags={"invoices"},
     *      summary="Updates an invoice",
     *      description="Handles the updating of an invoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        if ($request->entityIsDeleted($invoice)) {
            return $request->disallowUpdate();
        }

        if ($invoice->isLocked()) {
            return response()->json(['message' => ctrans('texts.locked_invoice')], 403);
        }

        $old_invoice = $invoice->line_items;

        $invoice = $this->invoice_repo->save($request->all(), $invoice);

        $invoice->service()
                ->triggeredActions($request)
                ->touchPdf()
                ->adjustInventory($old_invoice);

        event(new InvoiceWasUpdated($invoice, $invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $transaction = [
            'invoice' => $invoice->transaction_event(),
            'payment' => [],
            'client' => $invoice->client->transaction_event(),
            'credit' => [],
            'metadata' => [],
        ];

        TransactionLog::dispatch(TransactionEvent::INVOICE_UPDATED, $transaction, $invoice->company->db);

        return $this->itemResponse($invoice);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyInvoiceRequest $request
     * @param Invoice $invoice
     *
     * @return     Response
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/invoices/{id}",
     *      operationId="deleteInvoice",
     *      tags={"invoices"},
     *      summary="Deletes a invoice",
     *      description="Handles the deletion of an invoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyInvoiceRequest $request, Invoice $invoice)
    {
        $this->invoice_repo->delete($invoice);

        return $this->itemResponse($invoice->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Collection
     *
     * @OA\Post(
     *      path="/api/v1/invoices/bulk",
     *      operationId="bulkInvoices",
     *      tags={"invoices"},
     *      summary="Performs bulk actions on an array of invoices",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="User credentials",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The Bulk Action response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function bulk()
    {
        
        $action = request()->input('action');

        $ids = request()->input('ids');

        $invoices = Invoice::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if (! $invoices) {
            return response()->json(['message' => 'No Invoices Found']);
        }

        /*
         * Download Invoice/s
         */

        if ($action == 'bulk_download' && $invoices->count() > 1) {
            $invoices->each(function ($invoice) {
                if (auth()->user()->cannot('view', $invoice)) {
                    nlog("access denied");
                    return response()->json(['message' => ctrans('text.access_denied')]);
                }
            });

            ZipInvoices::dispatch($invoices, $invoices->first()->company, auth()->user());

            return response()->json(['message' => ctrans('texts.sent_message')], 200);
        }

        /*
         * Send the other actions to the switch
         */
        $invoices->each(function ($invoice, $key) use ($action) {
            if (auth()->user()->can('edit', $invoice)) {
                $this->performAction($invoice, $action, true);
            }
        });

        /* Need to understand which permission are required for the given bulk action ie. view / edit */

        return $this->listResponse(Invoice::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }

    /**
     * @OA\Get(
     *      path="/api/v1/invoices/{id}/{action}",
     *      operationId="actionInvoice",
     *      tags={"invoices"},
     *      summary="Performs a custom action on an invoice",
     *      description="Performs a custom action on an invoice.
     *
     *        The current range of actions are as follows
     *        - clone_to_invoice
     *        - clone_to_quote
     *        - history
     *        - delivery_note
     *        - mark_paid
     *        - download
     *        - archive
     *        - delete
     *        - email",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="action",
     *          in="path",
     *          description="The action string to be performed",
     *          example="clone_to_quote",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param ActionInvoiceRequest $request
     * @param Invoice $invoice
     * @param $action
     * @return \App\Http\Controllers\Response|\Illuminate\Http\JsonResponse|Response|mixed|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function action(ActionInvoiceRequest $request, Invoice $invoice, $action)
    {
        return $this->performAction($invoice, $action);
    }

    private function performAction(Invoice $invoice, $action, $bulk = false)
    {   
        /*If we are using bulk actions, we don't want to return anything */
        switch ($action) {
            case 'clone_to_invoice':
                $invoice = CloneInvoiceFactory::create($invoice, auth()->user()->id);

                return $this->itemResponse($invoice);
                break;
            case 'clone_to_quote':
                $quote = CloneInvoiceToQuoteFactory::create($invoice, auth()->user()->id);

                $this->entity_transformer = QuoteTransformer::class;
                $this->entity_type = Quote::class;

                return $this->itemResponse($quote);

                break;
            case 'history':
                // code...
                break;
            case 'delivery_note':
                // code...
                break;
            case 'mark_paid':
                if ($invoice->status_id == Invoice::STATUS_PAID || $invoice->is_deleted === true) {
                // if ($invoice->balance < 0 || $invoice->status_id == Invoice::STATUS_PAID || $invoice->is_deleted === true) {
                    return $this->errorResponse(['message' => ctrans('texts.invoice_cannot_be_marked_paid')], 400);
                }

                $invoice = $invoice->service()->markPaid()->save();

                if (! $bulk) {
                    return $this->itemResponse($invoice);
                }
                break;
            case 'mark_sent':
                $invoice->service()->markSent()->save();

                if (! $bulk) {
                    return $this->itemResponse($invoice);
                }
                break;
            case 'download':

                $file = $invoice->service()->getInvoicePdf();

                return response()->streamDownload(function () use($file) {
                        echo Storage::get($file);
                },  basename($file), ['Content-Type' => 'application/pdf']);

                break;
            case 'restore':
                $this->invoice_repo->restore($invoice);

                if (! $bulk) {
                    return $this->listResponse($invoice);
                }
                break;
            case 'archive':
                $this->invoice_repo->archive($invoice);

                if (! $bulk) {
                    return $this->listResponse($invoice);
                }
                break;
            case 'delete':

                $this->invoice_repo->delete($invoice);

                if (! $bulk) {
                    return $this->listResponse($invoice);
                }
                break;
            case 'cancel':
                $invoice = $invoice->service()->handleCancellation()->deletePdf()->touchPdf()->save();

                if (! $bulk) {
                    $this->itemResponse($invoice);
                }
                break;

            case 'email':
                //check query parameter for email_type and set the template else use calculateTemplate


                if (request()->has('email_type') && property_exists($invoice->company->settings, request()->input('email_type'))) {
                    $this->reminder_template = $invoice->client->getSetting(request()->input('email_type'));
                } else {
                    $this->reminder_template = $invoice->calculateTemplate('invoice');
                }

                BulkInvoiceJob::dispatch($invoice, $this->reminder_template);

                if (! $bulk) {
                    return response()->json(['message' => 'email sent'], 200);
                }
                break;

            case 'send_email':
                //check query parameter for email_type and set the template else use calculateTemplate


                if (request()->has('email_type') && property_exists($invoice->company->settings, request()->input('email_type'))) {
                    $this->reminder_template = $invoice->client->getSetting(request()->input('email_type'));
                } else {
                    $this->reminder_template = $invoice->calculateTemplate('invoice');
                }

                BulkInvoiceJob::dispatch($invoice, $this->reminder_template);

                if (! $bulk) {
                    return response()->json(['message' => 'email sent'], 200);
                }
                break;


            default:
                return response()->json(['message' => ctrans('texts.action_unavailable', ['action' => $action])], 400);
                break;
        }
    }

    /**
     * @OA\Get(
     *      path="/api/v1/invoice/{invitation_key}/download",
     *      operationId="downloadInvoice",
     *      tags={"invoices"},
     *      summary="Download a specific invoice by invitation key",
     *      description="Downloads a specific invoice",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="invitation_key",
     *          in="path",
     *          description="The Invoice Invitation Key",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the invoice pdf",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param $invitation_key
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadPdf($invitation_key)
    {
        $invitation = $this->invoice_repo->getInvitationByKey($invitation_key);

        if(!$invitation)
            return response()->json(["message" => "no record found"], 400);

        $contact = $invitation->contact;
        $invoice = $invitation->invoice;

        $file = $invoice->service()->getInvoicePdf($contact);

        $headers = ['Content-Type' => 'application/pdf'];

        if(request()->input('inline') == 'true')
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);

        return response()->streamDownload(function () use($file) {
                echo Storage::get($file);
        },  basename($file), $headers);
        
    }

    /**
     * @OA\Get(
     *      path="/api/v1/invoices/{id}/delivery_note",
     *      operationId="deliveryNote",
     *      tags={"invoices"},
     *      summary="Download a specific invoice delivery notes",
     *      description="Downloads a specific invoice delivery notes",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hahsed Id",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the invoice delivery note pdf",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param $invoice
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function deliveryNote(ShowInvoiceRequest $request, Invoice $invoice)
    {
        
        $file = $invoice->service()->getInvoiceDeliveryNote($invoice, $invoice->invitations->first()->contact);
        
        return response()->streamDownload(function () use($file) {
                echo Storage::get($file);
        },  basename($file), ['Content-Type' => 'application/pdf']);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadInvoiceRequest $request
     * @param Invoice $invoice
     * @return Response
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/invoices/{id}/upload",
     *      operationId="uploadInvoice",
     *      tags={"invoices"},
     *      summary="Uploads a document to a invoice",
     *      description="Handles the uploading of a document to a invoice",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function upload(UploadInvoiceRequest $request, Invoice $invoice)
    {
        if(!$this->checkFeature(Account::FEATURE_DOCUMENTS))
            return $this->featureFailure();
        
        if ($request->has('documents')) 
            $this->saveDocuments($request->file('documents'), $invoice);

        if ($request->has('file')) 
            $this->saveDocuments($request->file('documents'), $invoice);

        return $this->itemResponse($invoice->fresh());

    }    

    public function update_reminders(UpdateReminderRequest $request)
    {

        UpdateReminders::dispatch(auth()->user()->company());

        return response()->json(["message" => "Updating reminders"], 200);
    }
}
