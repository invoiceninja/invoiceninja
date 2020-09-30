<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\DataMapper\CompanySettings;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Factory\CloneInvoiceFactory;
use App\Factory\CloneInvoiceToQuoteFactory;
use App\Factory\InvoiceFactory;
use App\Filters\InvoiceFilters;
use App\Helpers\Email\InvoiceEmail;
use App\Http\Requests\Invoice\ActionInvoiceRequest;
use App\Http\Requests\Invoice\CreateInvoiceRequest;
use App\Http\Requests\Invoice\DestroyInvoiceRequest;
use App\Http\Requests\Invoice\EditInvoiceRequest;
use App\Http\Requests\Invoice\ShowInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Jobs\Invoice\CreateInvoicePdf;
use App\Jobs\Invoice\EmailInvoice;
use App\Jobs\Invoice\StoreInvoice;
use App\Jobs\Invoice\ZipInvoices;
use App\Jobs\Util\UnlinkFile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Repositories\InvoiceRepository;
use App\Transformers\InvoiceTransformer;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Class InvoiceController.
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
     * Show the list of Invoices.
     *
     * @param      \App\Filters\InvoiceFilters  $filters  The filters
     *
     * @return \Illuminate\Http\Response
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
        $invoices = Invoice::filter($filters);

        return $this->listResponse($invoices);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param      \App\Http\Requests\Invoice\CreateInvoiceRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
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
     * @param      \App\Http\Requests\Invoice\StoreInvoiceRequest  $request  The request
     *
     * @return \Illuminate\Http\Response
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
        $client = Client::find($request->input('client_id'));

        $invoice = $this->invoice_repo->save($request->all(), InvoiceFactory::create(auth()->user()->company()->id, auth()->user()->id));

        event(new InvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars()));

        $invoice = $invoice->service()->triggeredActions($request)->save();

        return $this->itemResponse($invoice);
    }

    /**
     * Display the specified resource.
     *
     * @param      \App\Http\Requests\Invoice\ShowInvoiceRequest  $request  The request
     * @param      \App\Models\Invoice                            $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
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
     * @param      \App\Http\Requests\Invoice\EditInvoiceRequest  $request  The request
     * @param      \App\Models\Invoice                            $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
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
     * @param      \App\Http\Requests\Invoice\UpdateInvoiceRequest  $request  The request
     * @param      \App\Models\Invoice                              $invoice  The invoice
     *
     * @return \Illuminate\Http\Response
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
            return response()->json(['message' => 'Invoice is locked, no modifications allowed']);
        }

        $invoice = $this->invoice_repo->save($request->all(), $invoice);

        UnlinkFile::dispatchNow(config('filesystems.default'),$invoice->client->invoice_filepath().$invoice->number.'.pdf');

        event(new InvoiceWasUpdated($invoice, $invoice->company, Ninja::eventVars()));

        return $this->itemResponse($invoice);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param      \App\Http\Requests\Invoice\DestroyInvoiceRequest  $request
     * @param      \App\Models\Invoice                               $invoice
     *
     * @return     \Illuminate\Http\Response
     *
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
        $invoice->delete();

        return response()->json([], 200);
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

        /*
         * WIP!
         */
        $action = request()->input('action');

        $ids = request()->input('ids');

        $invoices = Invoice::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if (! $invoices) {
            return response()->json(['message' => 'No Invoices Found']);
        }

        /*
         * Download Invoice/s
         */

        if ($action == 'download' && $invoices->count() > 1) {
            $invoices->each(function ($invoice) {
                if (auth()->user()->cannot('view', $invoice)) {
                    return response()->json(['message' => 'Insufficient privileges to access invoice '.$invoice->number]);
                }
            });

            ZipInvoices::dispatch($invoices, $invoices->first()->company, auth()->user()->email);

            return response()->json(['message' => 'Email Sent!'], 200);
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
     *		The current range of actions are as follows
     *		- clone_to_invoice
     *		- clone_to_quote
     *		- history
     *		- delivery_note
     *		- mark_paid
     *		- download
     *		- archive
     *		- delete
     *		- email",
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
                // todo build the quote transformer and return response here
                break;
            case 'history':
                // code...
                break;
            case 'delivery_note':
                // code...
                break;
            case 'mark_paid':
                if ($invoice->balance < 0 || $invoice->status_id == Invoice::STATUS_PAID || $invoice->is_deleted === true) {
                    return $this->errorResponse(['message' => 'Invoice cannot be marked as paid'], 400);
                }

                $invoice = $invoice->service()->markPaid();

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
                    return response()->streamDownload(function () use ($invoice) {
                        echo file_get_contents($invoice->pdf_file_path());
                    }, basename($invoice->pdf_file_path()));
                    //return response()->download(TempFile::path($invoice->pdf_file_path()), basename($invoice->pdf_file_path()));
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
                //need to make sure the invoice is cancelled first!!
                $invoice->service()->handleCancellation()->save();

                $this->invoice_repo->delete($invoice);

                if (! $bulk) {
                    return $this->listResponse($invoice);
                }
                break;
            case 'cancel':
                $invoice = $invoice->service()->handleCancellation()->save();

                if (! $bulk) {
                    $this->itemResponse($invoice);
                }
                break;
            case 'reverse':
                $invoice = $invoice->service()->handleReversal()->save();

                if (! $bulk) {
                    $this->itemResponse($invoice);
                }
                break;
            case 'email':
                //check query parameter for email_type and set the template else use calculateTemplate
                if (request()->has('email_type') && property_exists($invoice->company->settings, request()->input('email_type'))) {
                    $this->reminder_template = $invoice->client->getSetting(request()->input('email_type'));
                } else {
                    $this->reminder_template = $invoice->calculateTemplate();
                }

                //touch reminder1,2,3_sent + last_sent here if the email is a reminder.

                $invoice->service()->touchReminder($this->reminder_template)->save();

                $invoice->invitations->load('contact.client.country', 'invoice.client.country', 'invoice.company')->each(function ($invitation) use ($invoice) {
                    $email_builder = (new InvoiceEmail())->build($invitation, $this->reminder_template);

                    EmailInvoice::dispatch($email_builder, $invitation, $invoice->company);
                });

                if (! $bulk) {
                    return response()->json(['message' => 'email sent'], 200);
                }
                break;

            default:
                return response()->json(['message' => "The requested action `{$action}` is not available."], 400);
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
     */
    public function downloadPdf($invitation_key)
    {
        $invitation = $this->invoice_repo->getInvitationByKey($invitation_key);
        $contact = $invitation->contact;
        $invoice = $invitation->invoice;

        $file_path = $invoice->service()->getInvoicePdf($contact);

        return response()->download($file_path, basename($file_path));
    }
}
