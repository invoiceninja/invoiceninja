<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Input;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Product;
use App\Models\Invitation;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Mailers\ContactMailer as Mailer;

class InvoiceApiController extends Controller
{
    protected $invoiceRepo;

    public function __construct(InvoiceRepository $invoiceRepo, Mailer $mailer)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->mailer = $mailer;
    }

    public function index()
    {
        $invoices = Invoice::scope()->where('invoices.is_quote', '=', false)->orderBy('created_at', 'desc')->get();
        $invoices = Utils::remapPublicIds($invoices->toArray());

        $response = json_encode($invoices, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders(count($invoices));

        return Response::make($response, 200, $headers);
    }

    public function store()
    {
        $data = Input::all();
        $error = null;
                
        // check if the invoice number is set and unique
        if (!isset($data['invoice_number'])) {
            $data['invoice_number'] = Auth::user()->account->getNextInvoiceNumber();
        } else {
            $invoice = Invoice::scope()->where('invoice_number', '=', $data['invoice_number'])->first();
            if ($invoice) {
                $error = trans('validation.unique', ['attribute' => 'texts.invoice_number']);
            }
        }

        // check the client id is set and exists
        if (!isset($data['client_id'])) {
            $error = trans('validation.required', ['attribute' => 'client_id']);
        } else {
            $client = Client::scope($data['client_id'])->first();
            if (!$client) {
                $error = trans('validation.not_in', ['attribute' => 'client_id']);
            }
        }
        
        if ($error) {
            $response = json_encode($error, JSON_PRETTY_PRINT);
        } else {
            $data = self::prepareData($data);
            $invoice = $this->invoiceRepo->save(false, $data, false);

            $invitation = Invitation::createNew();
            $invitation->invoice_id = $invoice->id;
            $invitation->contact_id = $client->contacts[0]->id;
            $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
            $invitation->save();

            // prepare the return data
            $invoice->load('invoice_items');
            $invoice = $invoice->toArray();
            $invoice['link'] = $invitation->getLink();
            unset($invoice['account']);
            unset($invoice['client']);
            $invoice = Utils::remapPublicIds($invoice);
            $invoice['client_id'] = $client->public_id;
            
            $response = json_encode($invoice, JSON_PRETTY_PRINT);
        }

        $headers = Utils::getApiHeaders();
        
        return Response::make($response, $error ? 400 : 200, $headers);
    }

    private function prepareData($data)
    {
        $account = Auth::user()->account;
        $account->loadLocalizationSettings();
        
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
