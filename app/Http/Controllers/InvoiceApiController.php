<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Input;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Invitation;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Mailers\ContactMailer as Mailer;

class InvoiceApiController extends Controller
{
    protected $invoiceRepo;

    public function __construct(InvoiceRepository $invoiceRepo, ClientRepository $clientRepo, Mailer $mailer)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->clientRepo = $clientRepo;
        $this->mailer = $mailer;
    }

    public function index($clientPublicId = false)
    {
        $invoices = Invoice::scope()
                        ->with('client', 'invitations.account')
                        ->where('invoices.is_quote', '=', false);

        if ($clientPublicId) {
            $invoices->whereHas('client', function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            });
        }

        $invoices = $invoices->orderBy('created_at', 'desc')->get();

        // Add the first invitation link to the data
        foreach ($invoices as $key => $invoice) {
            foreach ($invoice->invitations as $subKey => $invitation) {
                $invoices[$key]['link'] = $invitation->getLink();
            }
            unset($invoice['invitations']);
        }

        $invoices = Utils::remapPublicIds($invoices);
                
        $response = json_encode($invoices, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders(count($invoices));

        return Response::make($response, 200, $headers);
    }

    public function store()
    {
        $data = Input::all();
        $error = null;
                
        if (isset($data['email'])) {
            $client = Client::scope()->whereHas('contacts', function($query) use ($data) {
                $query->where('email', '=', $data['email']);
            })->first();
            
            if (!$client) {
                $clientData = ['contact' => ['email' => $data['email']]];
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
                $error = $this->clientRepo->getErrors($clientData);
                if (!$error) {
                    $client = $this->clientRepo->save(false, $clientData, false);
                }
            }
        } else if (isset($data['client_id'])) {
            $client = Client::scope($data['client_id'])->first();
        }

        // check if the invoice number is set and unique
        if (!isset($data['invoice_number']) && !isset($data['id'])) {
            $data['invoice_number'] = Auth::user()->account->getNextInvoiceNumber(false, '', $client);
        } else if (isset($data['invoice_number'])) {
            $invoice = Invoice::scope()->where('invoice_number', '=', $data['invoice_number'])->first();
            if ($invoice) {
                $error = trans('validation.unique', ['attribute' => 'texts.invoice_number']);
            }
        }

        if (!$error) {
            if (!isset($data['client_id']) && !isset($data['email'])) {
                $error = trans('validation.', ['attribute' => 'client_id or email']);
            } else if (!$client) {
                $error = trans('validation.not_in', ['attribute' => 'client_id']);
            }
        }

        if ($error) {
            $response = json_encode($error, JSON_PRETTY_PRINT);
        } else {
            $data = self::prepareData($data, $client);
            $data['client_id'] = $client->id;
            $invoice = $this->invoiceRepo->save(false, $data, false);

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

            // prepare the return data
            $invoice = Invoice::scope($invoice->public_id)->with('client', 'invoice_items', 'invitations')->first();
            $invoice = Utils::remapPublicIds([$invoice]);

            $response = json_encode($invoice, JSON_PRETTY_PRINT);
        }

        $headers = Utils::getApiHeaders();
        
        return Response::make($response, $error ? 400 : 200, $headers);
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

        // hardcode some fields
        $fields = [
            'is_recurring' => false
        ];

        foreach ($fields as $key => $val) {
            $data[$key] = $val;
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
        if ($item['product_key'] && (!$item['cost'] || !$item['notes'])) {
            $product = Product::findProductByKey($item['product_key']);
            if ($product) {
                if (!$item['cost']) {
                    $item['cost'] = $product->cost;
                }
                if (!$item['notes']) {
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
}
