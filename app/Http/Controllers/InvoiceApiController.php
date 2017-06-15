<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Http\Requests\CreateInvoiceAPIRequest;
use App\Http\Requests\UpdateInvoiceAPIRequest;
use App\Jobs\SendInvoiceEmail;
use App\Jobs\SendPaymentEmail;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Product;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Auth;
use Input;
use Response;
use Utils;
use Validator;

class InvoiceApiController extends BaseAPIController
{
    protected $invoiceRepo;

    protected $entityType = ENTITY_INVOICE;

    public function __construct(InvoiceService $invoiceService, InvoiceRepository $invoiceRepo, ClientRepository $clientRepo, PaymentRepository $paymentRepo, PaymentService $paymentService)
    {
        parent::__construct();

        $this->invoiceRepo = $invoiceRepo;
        $this->clientRepo = $clientRepo;
        $this->paymentRepo = $paymentRepo;
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
    }

    /**
     * @SWG\Get(
     *   path="/invoices",
     *   summary="List invoices",
     *   operationId="listInvoices",
     *   tags={"invoice"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of invoices",
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
                        ->with('invoice_items', 'client')
                        ->orderBy('created_at', 'desc');

        // Filter by invoice number
        if ($invoiceNumber = Input::get('invoice_number')) {
            $invoices->whereInvoiceNumber($invoiceNumber);
        }

        return $this->listResponse($invoices);
    }

    /**
     * @SWG\Get(
     *   path="/invoices/{invoice_id}",
     *   summary="Retrieve an Invoice",
     *   tags={"invoice"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="invoice_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single invoice",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(InvoiceRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/invoices",
     *   summary="Create an invoice",
     *   tags={"invoice"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="invoice",
     *     @SWG\Schema(ref="#/definitions/Invoice")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New invoice",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateInvoiceAPIRequest $request)
    {
        $data = Input::all();
        $error = null;

        if (isset($data['email'])) {
            $email = $data['email'];
            $client = Client::scope()->whereHas('contacts', function ($query) use ($email) {
                $query->where('email', '=', $email);
            })->first();

            if (! $client) {
                $validator = Validator::make(['email' => $email], ['email' => 'email']);
                if ($validator->fails()) {
                    $messages = $validator->messages();

                    return $messages->first();
                }

                $clientData = ['contact' => ['email' => $email]];
                foreach ([
                    'name',
                    'address1',
                    'address2',
                    'city',
                    'state',
                    'postal_code',
                    'country_id',
                    'private_notes',
                    'currency_code',
                    'country_code',
                ] as $field) {
                    if (isset($data[$field])) {
                        $clientData[$field] = $data[$field];
                    }
                }
                foreach ([
                    'first_name',
                    'last_name',
                    'phone',
                ] as $field) {
                    if (isset($data[$field])) {
                        $clientData['contact'][$field] = $data[$field];
                    }
                }

                $client = $this->clientRepo->save($clientData);
            }
        } elseif (isset($data['client_id'])) {
            $client = Client::scope($data['client_id'])->firstOrFail();
        }

        $data = self::prepareData($data, $client);
        $data['client_id'] = $client->id;

        // in these cases the invoice needs to be set as public
        $isAutoBill = isset($data['auto_bill']) && filter_var($data['auto_bill'], FILTER_VALIDATE_BOOLEAN);
        $isEmailInvoice = isset($data['email_invoice']) && filter_var($data['email_invoice'], FILTER_VALIDATE_BOOLEAN);
        $isPaid = isset($data['paid']) && floatval($data['paid']);

        if ($isAutoBill || $isPaid || $isEmailInvoice) {
            $data['is_public'] = true;
        }

        $invoice = $this->invoiceService->save($data);
        $payment = false;

        if ($invoice->isStandard()) {
            if ($isAutoBill) {
                $payment = $this->paymentService->autoBillInvoice($invoice);
            } elseif ($isPaid) {
                $payment = $this->paymentRepo->save([
                    'invoice_id' => $invoice->id,
                    'client_id' => $client->id,
                    'amount' => $data['paid'],
                ]);
            }
        }

        if ($isEmailInvoice) {
            if ($payment) {
                app('App\Ninja\Mailers\ContactMailer')->sendPaymentConfirmation($payment);
                //$this->dispatch(new SendPaymentEmail($payment));
            } elseif (! $invoice->is_recurring) {
                app('App\Ninja\Mailers\ContactMailer')->sendInvoice($invoice);
                //$this->dispatch(new SendInvoiceEmail($invoice));
            }
        }

        $invoice = Invoice::scope($invoice->public_id)
                        ->with('client', 'invoice_items', 'invitations')
                        ->first();

        if (isset($data['download_invoice']) && boolval($data['download_invoice'])) {
            return $this->fileReponse($invoice->getFileName(), $invoice->getPDFString());
        }

        return $this->itemResponse($invoice);
    }

    private function prepareData($data, $client)
    {
        $account = Auth::user()->account;
        $account->loadLocalizationSettings($client);

        // set defaults for optional fields
        $fields = [
            'discount' => 0,
            'is_amount_discount' => false,
            'terms' => '',
            'invoice_footer' => '',
            'public_notes' => '',
            'po_number' => '',
            'invoice_design_id' => $account->invoice_design_id,
            'invoice_items' => [],
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_taxes1' => false,
            'custom_taxes2' => false,
            'partial' => 0,
        ];

        if (! isset($data['invoice_status_id']) || $data['invoice_status_id'] == 0) {
            $data['invoice_status_id'] = INVOICE_STATUS_DRAFT;
        }

        if (! isset($data['invoice_date'])) {
            $fields['invoice_date_sql'] = date_create()->format('Y-m-d');
        }
        if (! isset($data['due_date'])) {
            $fields['due_date_sql'] = false;
        }

        if (isset($data['is_quote']) && filter_var($data['is_quote'], FILTER_VALIDATE_BOOLEAN)) {
            $fields['invoice_design_id'] = $account->quote_design_id;
        }

        foreach ($fields as $key => $val) {
            if (! isset($data[$key])) {
                $data[$key] = $val;
            }
        }

        // initialize the line items
        if (isset($data['product_key']) || isset($data['cost']) || isset($data['notes']) || isset($data['qty'])) {
            $data['invoice_items'] = [self::prepareItem($data)];
            // make sure the tax isn't applied twice (for the invoice and the line item)
            unset($data['invoice_items'][0]['tax_name1']);
            unset($data['invoice_items'][0]['tax_rate1']);
            unset($data['invoice_items'][0]['tax_name2']);
            unset($data['invoice_items'][0]['tax_rate2']);
        } else {
            foreach ($data['invoice_items'] as $index => $item) {
                $data['invoice_items'][$index] = self::prepareItem($item);
            }
        }

        return $data;
    }

    private function prepareItem($item)
    {
        // if only the product key is set we'll load the cost and notes
        if (! empty($item['product_key']) && empty($item['cost']) && empty($item['notes'])) {
            $product = Product::findProductByKey($item['product_key']);
            if ($product) {
                if (empty($item['cost'])) {
                    $item['cost'] = $product->cost;
                }
                if (empty($item['notes'])) {
                    $item['notes'] = $product->notes;
                }
            }
        }

        $fields = [
            'cost' => 0,
            'product_key' => '',
            'notes' => '',
            'qty' => 1,
        ];

        foreach ($fields as $key => $val) {
            if (! isset($item[$key])) {
                $item[$key] = $val;
            }
        }

        return $item;
    }

    public function emailInvoice(InvoiceRequest $request)
    {
        $invoice = $request->entity();

        //$this->dispatch(new SendInvoiceEmail($invoice));
        $result = app('App\Ninja\Mailers\ContactMailer')->sendInvoice($invoice);

        if ($result !== true) {
            return $this->errorResponse($result, 500);
        }

        $headers = Utils::getApiHeaders();
        $response = json_encode(['message' => RESULT_SUCCESS], JSON_PRETTY_PRINT);

        return Response::make($response, 200, $headers);
    }

    /**
     * @SWG\Put(
     *   path="/invoices/{invoice_id}",
     *   summary="Update an invoice",
     *   tags={"invoice"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="invoice_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="invoice",
     *     @SWG\Schema(ref="#/definitions/Invoice")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated invoice",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
     */
    public function update(UpdateInvoiceAPIRequest $request, $publicId)
    {
        if ($request->action == ACTION_CONVERT) {
            $quote = $request->entity();
            $invoice = $this->invoiceRepo->cloneInvoice($quote, $quote->id);

            return $this->itemResponse($invoice);
        } elseif ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $this->invoiceService->save($data, $request->entity());

        $invoice = Invoice::scope($publicId)
                        ->with('client', 'invoice_items', 'invitations')
                        ->firstOrFail();

        return $this->itemResponse($invoice);
    }

    /**
     * @SWG\Delete(
     *   path="/invoices/{invoice_id}",
     *   summary="Delete an invoice",
     *   tags={"invoice"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="invoice_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted invoice",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateInvoiceAPIRequest $request)
    {
        $invoice = $request->entity();

        $this->invoiceRepo->delete($invoice);

        return $this->itemResponse($invoice);
    }

    public function download(InvoiceRequest $request)
    {
        $invoice = $request->entity();

        return $this->fileReponse($invoice->getFileName(), $invoice->getPDFString());
    }
}
