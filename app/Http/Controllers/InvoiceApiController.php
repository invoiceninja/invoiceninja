<?php namespace App\Http\Controllers;

use Auth;
use Illuminate\Support\Facades\Request;
use Utils;
use Response;
use Input;
use Validator;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Invitation;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\InvoiceTransformer;
use App\Http\Requests\CreateInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;

class InvoiceApiController extends BaseAPIController
{
    protected $invoiceRepo;

    public function __construct(InvoiceRepository $invoiceRepo, ClientRepository $clientRepo, Mailer $mailer)
    {
        parent::__construct();

        $this->invoiceRepo = $invoiceRepo;
        $this->clientRepo = $clientRepo;
        $this->mailer = $mailer;
    }

    /**
     * @SWG\Get(
     *   path="/invoices",
     *   summary="List of invoices",
     *   tags={"invoice"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with invoices",
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
        $paginator = Invoice::scope()->withTrashed();
        $invoices = Invoice::scope()->withTrashed()
                        ->with(array_merge(['invoice_items'], $this->getIncluded()));

        if ($clientPublicId = Input::get('client_id')) {
            $filter = function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            };
            $invoices->whereHas('client', $filter);
            $paginator->whereHas('client', $filter);
        }

        $invoices = $invoices->orderBy('created_at', 'desc')->paginate();

        /*
        // Add the first invitation link to the data
        foreach ($invoices as $key => $invoice) {
            foreach ($invoice->invitations as $subKey => $invitation) {
                $invoices[$key]['link'] = $invitation->getLink();
            }
            unset($invoice['invitations']);
        }
        */

        $transformer = new InvoiceTransformer(Auth::user()->account, Input::get('serializer'));
        $paginator = $paginator->paginate();

        $data = $this->createCollection($invoices, $transformer, 'invoices', $paginator);

        return $this->response($data);
    }


    /**
     * @SWG\Post(
     *   path="/invoices",
     *   tags={"invoice"},
     *   summary="Create an invoice",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
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
    public function store(CreateInvoiceRequest $request)
    {
        $data = Input::all();
        $error = null;

        if (isset($data['email'])) {
            $email = $data['email'];
            $client = Client::scope()->whereHas('contacts', function($query) use ($email) {
                $query->where('email', '=', $email);
            })->first();
            
            if (!$client) {
                $validator = Validator::make(['email'=>$email], ['email' => 'email']);
                if ($validator->fails()) {
                    $messages = $validator->messages();
                    return $messages->first();
                }

                $clientData = ['contact' => ['email' => $email]];
                foreach (['name', 'private_notes'] as $field) {
                    if (isset($data[$field])) {
                        $clientData[$field] = $data[$field];
                    }
                }
                foreach (['first_name', 'last_name'] as $field) {
                    if (isset($data[$field])) {
                        $clientData[$field] = $data[$field];
                    }
                }

                $client = $this->clientRepo->save($clientData);
            }
        } else if (isset($data['client_id'])) {
            $client = Client::scope($data['client_id'])->firstOrFail();
        }

        $data = self::prepareData($data, $client);
        $data['client_id'] = $client->id;
        $invoice = $this->invoiceRepo->save($data);

        if (!isset($data['id'])) {
            $invitation = Invitation::createNew();
            $invitation->invoice_id = $invoice->id;
            $invitation->contact_id = $client->contacts[0]->id;
            $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
            $invitation->save();
        }

        if (isset($data['email_invoice']) && $data['email_invoice']) {
            $this->mailer->sendInvoice($invoice);
        }

        $invoice = Invoice::scope($invoice->public_id)->with('client', 'invoice_items', 'invitations')->first();
        $transformer = new InvoiceTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($invoice, $transformer, 'invoice');

        return $this->response($data);
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
            'partial' => 0
        ];

        if (!isset($data['invoice_date'])) {
            $fields['invoice_date_sql'] = date_create()->format('Y-m-d');
        }
        if (!isset($data['due_date'])) {
            $fields['due_date_sql'] = false;
        }

        foreach ($fields as $key => $val) {
            if (!isset($data[$key])) {
                $data[$key] = $val;
            }
        }

        // initialize the line items
        if (isset($data['product_key']) || isset($data['cost']) || isset($data['notes']) || isset($data['qty'])) {
            $data['invoice_items'] = [self::prepareItem($data)];

            // make sure the tax isn't applied twice (for the invoice and the line item)
            unset($data['invoice_items'][0]['tax_name']);
            unset($data['invoice_items'][0]['tax_rate']);
        } else {
            foreach ($data['invoice_items'] as $index => $item) {
                $data['invoice_items'][$index] = self::prepareItem($item);
            }
        }

        return $data;
    }

    private function prepareItem($item)
    {
        $fields = [
            'cost' => 0,
            'product_key' => '',
            'notes' => '',
            'qty' => 1
        ];

        foreach ($fields as $key => $val) {
            if (!isset($item[$key])) {
                $item[$key] = $val;
            }
        }

        // if only the product key is set we'll load the cost and notes
        if ($item['product_key'] && (is_null($item['cost']) || is_null($item['notes']))) {
            $product = Product::findProductByKey($item['product_key']);
            if ($product) {
                if (is_null($item['cost'])) {
                    $item['cost'] = $product->cost;
                }
                if (is_null($item['notes'])) {
                    $item['notes'] = $product->notes;
                }
            }
        }

        return $item;
    }

    public function emailInvoice()
    {
        $data = Input::all();
        $error = null;

        if (!isset($data['id'])) {
            $error = trans('validation.required', ['attribute' => 'id']);
        } else {
            $invoice = Invoice::scope($data['id'])->first();
            if (!$invoice) {
                $error = trans('validation.not_in', ['attribute' => 'id']);
            } else {
                $this->mailer->sendInvoice($invoice);
            }
        }

        if ($error) {
            $response = json_encode($error, JSON_PRETTY_PRINT);
        } else {
            $response = json_encode(RESULT_SUCCESS, JSON_PRETTY_PRINT);
        }

        $headers = Utils::getApiHeaders();
        return Response::make($response, $error ? 400 : 200, $headers);
    }

        /**
         * @SWG\Put(
         *   path="/invoices",
         *   tags={"invoice"},
         *   summary="Update an invoice",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Invoice")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Update invoice",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */
    public function update(UpdateInvoiceRequest $request, $publicId)
    {
        if ($request->action == ACTION_ARCHIVE) {
            $invoice = Invoice::scope($publicId)->firstOrFail();
            $this->invoiceRepo->archive($invoice);

            $transformer = new InvoiceTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($invoice, $transformer, 'invoice');

            return $this->response($data);
        }
        else if ($request->action == ACTION_CONVERT) {
            $quote = Invoice::scope($publicId)->firstOrFail();
            $invoice = $this->invoiceRepo->cloneInvoice($quote, $quote->id);

            $transformer = new InvoiceTransformer(\Auth::user()->account, Input::get('serializer'));
            $data = $this->createItem($invoice, $transformer, 'invoice');

            return $this->response($data);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $this->invoiceRepo->save($data);

        $invoice = Invoice::scope($publicId)->with('client', 'invoice_items', 'invitations')->firstOrFail();
        $transformer = new InvoiceTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($invoice, $transformer, 'invoice');

        return $this->response($data);
    }

        /**
         * @SWG\Delete(
         *   path="/invoices",
         *   tags={"invoice"},
         *   summary="Delete an invoice",
         *   @SWG\Parameter(
         *     in="body",
         *     name="body",
         *     @SWG\Schema(ref="#/definitions/Invoice")
         *   ),
         *   @SWG\Response(
         *     response=200,
         *     description="Delete invoice",
         *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Invoice"))
         *   ),
         *   @SWG\Response(
         *     response="default",
         *     description="an ""unexpected"" error"
         *   )
         * )
         */

    public function destroy($publicId)
    {
        $data['public_id'] = $publicId;
        $invoice = Invoice::scope($publicId)->firstOrFail();

        $this->invoiceRepo->delete($invoice);

        $transformer = new InvoiceTransformer(\Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($invoice, $transformer, 'invoice');

        return $this->response($data);

    }

}
