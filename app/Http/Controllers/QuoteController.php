<?php namespace App\Http\Controllers;

use Auth;
use Input;
use Redirect;
use Utils;
use View;
use Cache;

use App\Models\Account;
use App\Models\Client;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Industry;
use App\Models\InvoiceDesign;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Size;
use App\Models\TaxRate;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\TaxRateRepository;

class QuoteController extends BaseController
{
    protected $mailer;
    protected $invoiceRepo;
    protected $clientRepo;
    protected $taxRateRepo;

    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, ClientRepository $clientRepo, TaxRateRepository $taxRateRepo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->clientRepo = $clientRepo;
        $this->taxRateRepo = $taxRateRepo;
    }

    public function index()
    {
        if (!Utils::isPro()) {
            return Redirect::to('/invoices/create');
        }

        $data = [
          'title' => trans('texts.quotes'),
          'entityType' => ENTITY_QUOTE,
          'columns' => Utils::trans(['checkbox', 'quote_number', 'client', 'quote_date', 'quote_total', 'due_date', 'status', 'action']),
        ];

    /*
    if (Invoice::scope()->where('is_recurring', '=', true)->count() > 0)
    {
      $data['secEntityType'] = ENTITY_RECURRING_INVOICE;
      $data['secColumns'] = Utils::trans(['checkbox', 'frequency', 'client', 'start_date', 'end_date', 'quote_total', 'action']);
    }
    */

    return View::make('list', $data);
    }

    public function clientIndex()
    {
        $invitationKey = Session::get('invitation_key');
        if (!$invitationKey) {
            return Redirect::to('/setup');
        }

        $invitation = Invitation::with('account')->where('invitation_key', '=', $invitationKey)->first();
        $color = $invitation->account->primary_color ? $invitation->account->primary_color : '#0b4d78';
        
        $data = [
          'color' => $color,
          'hideLogo' => Session::get('white_label'),
          'title' => trans('texts.quotes'),
          'entityType' => ENTITY_QUOTE,
          'columns' => Utils::trans(['quote_number', 'quote_date', 'quote_total', 'due_date']),
        ];

        return View::make('public_list', $data);
    }

    public function getDatatable($clientPublicId = null)
    {
        $accountId = Auth::user()->account_id;
        $search = Input::get('sSearch');

        return $this->invoiceRepo->getDatatable($accountId, $clientPublicId, ENTITY_QUOTE, $search);
    }

    public function getClientDatatable()
    {
        $search = Input::get('sSearch');
        $invitationKey = Session::get('invitation_key');
        $invitation = Invitation::where('invitation_key', '=', $invitationKey)->first();

        if (!$invitation || $invitation->is_deleted) {
            return [];
        }

        $invoice = $invitation->invoice;

        if (!$invoice || $invoice->is_deleted) {
            return [];
        }

        return $this->invoiceRepo->getClientDatatable($invitation->contact_id, ENTITY_QUOTE, $search);
    }

    public function create($clientPublicId = 0)
    {
        if (!Utils::isPro()) {
            return Redirect::to('/invoices/create');
        }

        $client = null;
        $invoiceNumber = Auth::user()->account->getNextInvoiceNumber(true);
        $account = Account::with('country')->findOrFail(Auth::user()->account_id);

        if ($clientPublicId) {
            $client = Client::scope($clientPublicId)->firstOrFail();
        }

        $data = array(
        'account' => $account,
        'invoice' => null,
        'data' => Input::old('data'),
        'invoiceNumber' => $invoiceNumber,
        'method' => 'POST',
        'url' => 'invoices',
        'title' => trans('texts.new_quote'),
        'client' => $client, );
        $data = array_merge($data, self::getViewModel());

        return View::make('invoices.edit', $data);
    }

    private static function getViewModel()
    {
        return [
          'entityType' => ENTITY_QUOTE,
          'account' => Auth::user()->account,
          'products' => Product::scope()->orderBy('id')->get(array('product_key', 'notes', 'cost', 'qty')),
          'countries' => Cache::get('countries'),
          'clients' => Client::scope()->with('contacts', 'country')->orderBy('name')->get(),
          'taxRates' => TaxRate::scope()->orderBy('name')->get(),
          'currencies' => Cache::get('currencies'),
          'sizes' => Cache::get('sizes'),
          'paymentTerms' => Cache::get('paymentTerms'),
          'industries' => Cache::get('industries'),
          'invoiceDesigns' => InvoiceDesign::availableDesigns(),
          'invoiceLabels' => Auth::user()->account->getInvoiceLabels()
        ];
    }

    public function bulk()
    {
        $action = Input::get('action');

        if ($action == 'convert') {
            $invoice = Invoice::with('invoice_items')->scope(Input::get('id'))->firstOrFail();
            $clone = $this->invoiceRepo->cloneInvoice($invoice, $invoice->id);

            Session::flash('message', trans('texts.converted_to_invoice'));
            return Redirect::to('invoices/'.$clone->public_id);
        }

        $statusId = Input::get('statusId');
        $ids = Input::get('id') ? Input::get('id') : Input::get('ids');
        $count = $this->invoiceRepo->bulk($ids, $action, $statusId);

        if ($count > 0) {
            $key = $action == 'mark' ? "updated_quote" : "{$action}d_quote";
            $message = Utils::pluralize($key, $count);
            Session::flash('message', $message);
        }

        return Redirect::to('quotes');
    }

    public function approve($invitationKey)
    {
        $invitation = Invitation::with('invoice.invoice_items', 'invoice.invitations')->where('invitation_key', '=', $invitationKey)->firstOrFail();
        $invoice = $invitation->invoice;

        if ($invoice->is_quote && !$invoice->quote_invoice_id) {
            Activity::approveQuote($invitation);
            $invoice = $this->invoiceRepo->cloneInvoice($invoice, $invoice->id);
            Session::flash('message', trans('texts.converted_to_invoice'));

            foreach ($invoice->invitations as $invitationClone) {
                if ($invitation->contact_id == $invitationClone->contact_id) {
                    $invitationKey = $invitationClone->invitation_key;
                }
            }
        }

        return Redirect::to("view/{$invitationKey}");
    }
}
