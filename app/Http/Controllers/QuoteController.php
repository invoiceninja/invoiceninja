<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Models\Account;
use App\Models\Client;
use App\Models\Country;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\InvoiceDesign;
use App\Models\Product;
use App\Models\TaxRate;
use App\Ninja\Datatables\InvoiceDatatable;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Services\InvoiceService;
use Auth;
use Cache;
use Input;
use Redirect;
use Session;
use Utils;
use View;

class QuoteController extends BaseController
{
    protected $mailer;
    protected $invoiceRepo;
    protected $clientRepo;
    protected $invoiceService;
    protected $entityType = ENTITY_INVOICE;

    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, ClientRepository $clientRepo, InvoiceService $invoiceService)
    {
        // parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->clientRepo = $clientRepo;
        $this->invoiceService = $invoiceService;
    }

    public function index()
    {
        $datatable = new InvoiceDatatable();
        $datatable->entityType = ENTITY_QUOTE;

        $data = [
          'title' => trans('texts.quotes'),
          'entityType' => ENTITY_QUOTE,
          'datatable' => $datatable,
        ];

        return response()->view('list_wrapper', $data);
    }

    public function getDatatable($clientPublicId = null)
    {
        $accountId = Auth::user()->account_id;
        $search = Input::get('sSearch');

        return $this->invoiceService->getDatatable($accountId, $clientPublicId, ENTITY_QUOTE, $search);
    }

    public function create(InvoiceRequest $request, $clientPublicId = 0)
    {
        if (! Utils::hasFeature(FEATURE_QUOTES)) {
            return Redirect::to('/invoices/create');
        }

        $account = Auth::user()->account;
        $clientId = null;
        if ($clientPublicId) {
            $clientId = Client::getPrivateId($clientPublicId);
        }
        $invoice = $account->createInvoice(ENTITY_QUOTE, $clientId);
        $invoice->public_id = 0;

        $data = [
            'entityType' => $invoice->getEntityType(),
            'invoice' => $invoice,
            'data' => Input::old('data'),
            'method' => 'POST',
            'url' => 'invoices',
            'title' => trans('texts.new_quote'),
        ];
        $data = array_merge($data, self::getViewModel());

        return View::make('invoices.edit', $data);
    }

    private static function getViewModel()
    {
        $account = Auth::user()->account;

        return [
          'entityType' => ENTITY_QUOTE,
          'account' => $account,
          'products' => Product::scope()->orderBy('product_key')->get(),
          'taxRateOptions' => $account->present()->taxRateOptions,
          'countries' => Cache::get('countries'),
          'clients' => Client::scope()->with('contacts', 'country')->orderBy('name')->get(),
          'taxRates' => TaxRate::scope()->orderBy('name')->get(),
          'currencies' => Cache::get('currencies'),
          'sizes' => Cache::get('sizes'),
          'paymentTerms' => Cache::get('paymentTerms'),
          'languages' => Cache::get('languages'),
          'industries' => Cache::get('industries'),
          'invoiceDesigns' => InvoiceDesign::getDesigns(),
          'invoiceFonts' => Cache::get('fonts'),
          'invoiceLabels' => Auth::user()->account->getInvoiceLabels(),
          'isRecurring' => false,
        ];
    }

    public function bulk()
    {
        $action = Input::get('bulk_action') ?: Input::get('action');
        ;
        $ids = Input::get('bulk_public_id') ?: (Input::get('public_id') ?: Input::get('ids'));

        if ($action == 'convert') {
            $invoice = Invoice::with('invoice_items')->scope($ids)->firstOrFail();
            $clone = $this->invoiceService->convertQuote($invoice);

            Session::flash('message', trans('texts.converted_to_invoice'));

            return Redirect::to('invoices/'.$clone->public_id);
        }

        $count = $this->invoiceService->bulk($ids, $action);

        if ($count > 0) {
            $key = $action == 'markSent' ? 'updated_quote' : "{$action}d_quote";
            $message = Utils::pluralize($key, $count);
            Session::flash('message', $message);
        }

        return $this->returnBulk(ENTITY_QUOTE, $action, $ids);
    }

    public function approve($invitationKey)
    {
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.invitations')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;

        $invitationKey = $this->invoiceService->approveQuote($invoice, $invitation);
        Session::flash('message', trans('texts.quote_is_approved'));

        return Redirect::to("view/{$invitationKey}");
    }
}
