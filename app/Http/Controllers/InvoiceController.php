<?php namespace App\Http\Controllers;

use Auth;
use Session;
use Utils;
use View;
use Input;
use Cache;
use Redirect;
use DB;
use Event;
use URL;
use Datatable;
use Request;
use DropdownButton;
use App\Models\Invoice;
use App\Models\Invitation;
use App\Models\Client;
use App\Models\Account;
use App\Models\Product;
use App\Models\Country;
use App\Models\TaxRate;
use App\Models\Currency;
use App\Models\Size;
use App\Models\Industry;
use App\Models\PaymentTerm;
use App\Models\InvoiceDesign;
use App\Models\AccountGateway;
use App\Models\Activity;
use App\Models\Gateway;
use App\Ninja\Mailers\ContactMailer as Mailer;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\ClientRepository;
use App\Events\InvoiceViewed;

class InvoiceController extends BaseController
{
    protected $mailer;
    protected $invoiceRepo;
    protected $clientRepo;

    public function __construct(Mailer $mailer, InvoiceRepository $invoiceRepo, ClientRepository $clientRepo)
    {
        parent::__construct();

        $this->mailer = $mailer;
        $this->invoiceRepo = $invoiceRepo;
        $this->clientRepo = $clientRepo;
    }

    public function index()
    {
        $data = [
            'title' => trans('texts.invoices'),
            'entityType' => ENTITY_INVOICE,
            'columns' => Utils::trans(['checkbox', 'invoice_number', 'client', 'invoice_date', 'invoice_total', 'balance_due', 'due_date', 'status', 'action']),
        ];

        $recurringInvoices = Invoice::scope()->where('is_recurring', '=', true);

        if (Session::get('show_trash:invoice')) {
            $recurringInvoices->withTrashed();
        } else {
            $recurringInvoices->join('clients', 'clients.id', '=', 'invoices.client_id')
                                ->where('clients.deleted_at', '=', null);
        }

        if ($recurringInvoices->count() > 0) {
            $data['secEntityType'] = ENTITY_RECURRING_INVOICE;
            $data['secColumns'] = Utils::trans(['checkbox', 'frequency', 'client', 'start_date', 'end_date', 'invoice_total', 'action']);
        }

        return View::make('list', $data);
    }

    public function getDatatable($clientPublicId = null)
    {
        $accountId = Auth::user()->account_id;
        $search = Input::get('sSearch');

        return $this->invoiceRepo->getDatatable($accountId, $clientPublicId, ENTITY_INVOICE, $search);
    }

    public function getRecurringDatatable($clientPublicId = null)
    {
        $query = $this->invoiceRepo->getRecurringInvoices(Auth::user()->account_id, $clientPublicId, Input::get('sSearch'));
        $table = Datatable::query($query);

        if (!$clientPublicId) {
            $table->addColumn('checkbox', function ($model) { return '<input type="checkbox" name="ids[]" value="'.$model->public_id.'" '.Utils::getEntityRowClass($model).'>'; });
        }

        $table->addColumn('frequency', function ($model) { return link_to('invoices/'.$model->public_id, $model->frequency); });

        if (!$clientPublicId) {
            $table->addColumn('client_name', function ($model) { return link_to('clients/'.$model->client_public_id, Utils::getClientDisplayName($model)); });
        }

        return $table->addColumn('start_date', function ($model) { return Utils::fromSqlDate($model->start_date); })
            ->addColumn('end_date', function ($model) { return Utils::fromSqlDate($model->end_date); })
            ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id); })
            ->addColumn('dropdown', function ($model) {
            if ($model->is_deleted) {
                return '<div style="height:38px"/>';
            }

            $str = '<div class="btn-group tr-action" style="visibility:hidden;">
                        <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                            '.trans('texts.select').' <span class="caret"></span>
                        </button>
                        <ul class="dropdown-menu" role="menu">';

            if (!$model->deleted_at || $model->deleted_at == '0000-00-00') {
                $str .= '<li><a href="'.URL::to('invoices/'.$model->public_id.'/edit').'">'.trans('texts.edit_invoice').'</a></li>
										    <li class="divider"></li>
										    <li><a href="javascript:archiveEntity('.$model->public_id.')">'.trans('texts.archive_invoice').'</a></li>
										    <li><a href="javascript:deleteEntity('.$model->public_id.')">'.trans('texts.delete_invoice').'</a></li>';
            } else {
                $str .= '<li><a href="javascript:restoreEntity('.$model->public_id.')">'.trans('texts.restore_invoice').'</a></li>';
            }

            return $str.'</ul>
                    </div>';

            })
            ->make();
    }

    public function view($invitationKey)
    {
        if (!$invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return response()->view('error', [
                'error' => trans('texts.invoice_not_found'),
                'hideHeader' => true,
            ]);
        }

        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $invoice->account;

        if (!$account->checkSubdomain(Request::server('HTTP_HOST'))) {
            return response()->view('error', [
                'error' => trans('texts.invoice_not_found'),
                'hideHeader' => true,
            ]);
        }

        if (!Input::has('phantomjs') && !Session::has($invitationKey) && (!Auth::check() || Auth::user()->account_id != $invoice->account_id)) {
            Activity::viewInvoice($invitation);
            Event::fire(new InvoiceViewed($invoice));
        }

        Session::set($invitationKey, true); // track this invitation has been seen
        Session::set('invitation_key', $invitationKey); // track current invitation

        $account->loadLocalizationSettings($client);

        $invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
        $invoice->due_date = Utils::fromSqlDate($invoice->due_date);
        $invoice->is_pro = $account->isPro();
        
        if ($invoice->invoice_design_id == CUSTOM_DESIGN) {
            $invoice->invoice_design->javascript = $account->custom_design;
        } else {
            $invoice->invoice_design->javascript = $invoice->invoice_design->pdfmake;
        }

        $contact = $invitation->contact;
        $contact->setVisible([
            'first_name',
            'last_name',
            'email',
            'phone',
        ]);

        $paymentTypes = $this->getPaymentTypes($client, $invitation);
        $paymentURL = '';
        if (count($paymentTypes)) {
            $paymentURL = $paymentTypes[0]['url'];
            if (!$account->isGatewayConfigured(GATEWAY_PAYPAL_EXPRESS)) {
                $paymentURL = URL::to($paymentURL);
            }
        }

        $showApprove = $invoice->quote_invoice_id ? false : true;
        if ($invoice->due_date) {
            $showApprove = time() < strtotime($invoice->due_date);
        }

        $data = array(
            'showApprove' => $showApprove,
            'showBreadcrumbs' => false,
            'hideLogo' => $account->isWhiteLabel(),
            'hideHeader' => $account->isNinjaAccount(),
            'invoice' => $invoice->hidePrivateFields(),
            'invitation' => $invitation,
            'invoiceLabels' => $account->getInvoiceLabels(),
            'contact' => $contact,
            'paymentTypes' => $paymentTypes,
            'paymentURL' => $paymentURL,
            'phantomjs' => Input::has('phantomjs'),
        );

        return View::make('invoices.view', $data);
    }

    private function getPaymentTypes($client, $invitation)
    {
        $paymentTypes = [];
        $account = $client->account;

        if ($client->getGatewayToken()) {
            $paymentTypes[] = [
                'url' => URL::to("payment/{$invitation->invitation_key}/token"), 'label' => trans('texts.use_card_on_file')
            ];
        }
        foreach(Gateway::$paymentTypes as $type) {
            if ($account->getGatewayByType($type)) {
                $typeLink = strtolower(str_replace('PAYMENT_TYPE_', '', $type));
                $url = URL::to("/payment/{$invitation->invitation_key}/{$typeLink}");

                // PayPal doesn't allow being run in an iframe so we need to open in new tab
                if ($type === PAYMENT_TYPE_PAYPAL && $account->iframe_url) {
                    $url = 'javascript:window.open("'.$url.'", "_blank")';
                }
                $paymentTypes[] = [
                    'url' => $url, 'label' => trans('texts.'.strtolower($type))
                ];
            }
        }

        return $paymentTypes;
    }

    public function edit($publicId, $clone = false)
    {
        $account = Auth::user()->account;
        $invoice = Invoice::scope($publicId)->withTrashed()->with('invitations', 'account.country', 'client.contacts', 'client.country', 'invoice_items')->firstOrFail();
        $entityType = $invoice->getEntityType();
        
        $contactIds = DB::table('invitations')
            ->join('contacts', 'contacts.id', '=', 'invitations.contact_id')
            ->where('invitations.invoice_id', '=', $invoice->id)
            ->where('invitations.account_id', '=', Auth::user()->account_id)
            ->where('invitations.deleted_at', '=', null)
            ->select('contacts.public_id')->lists('public_id');

        if ($clone) {
            $invoice->id = null;
            $invoice->invoice_number = $account->getNextInvoiceNumber($invoice);
            $invoice->balance = $invoice->amount;
            $invoice->invoice_status_id = 0;
            $invoice->invoice_date = date_create()->format('Y-m-d');
            $method = 'POST';
            $url = "{$entityType}s";
        } else {
            Utils::trackViewed($invoice->getDisplayName().' - '.$invoice->client->getDisplayName(), $invoice->getEntityType());
            $method = 'PUT';
            $url = "{$entityType}s/{$publicId}";
        }

        $invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
        $invoice->due_date = Utils::fromSqlDate($invoice->due_date);
        $invoice->start_date = Utils::fromSqlDate($invoice->start_date);
        $invoice->end_date = Utils::fromSqlDate($invoice->end_date);
        $invoice->last_sent_date = Utils::fromSqlDate($invoice->last_sent_date);
        $invoice->is_pro = Auth::user()->isPro();

        $actions = [
            ['url' => 'javascript:onCloneClick()', 'label' => trans("texts.clone_{$entityType}")],
            ['url' => URL::to("{$entityType}s/{$entityType}_history/{$invoice->public_id}"), 'label' => trans("texts.view_history")],
            DropdownButton::DIVIDER
        ];

        if ($invoice->invoice_status_id < INVOICE_STATUS_SENT && !$invoice->is_recurring) {
            $actions[] = ['url' => 'javascript:onMarkClick()', 'label' => trans("texts.mark_sent")];
        }

        if ($entityType == ENTITY_QUOTE) {
            if ($invoice->quote_invoice_id) {
                $actions[] = ['url' => URL::to("invoices/{$invoice->quote_invoice_id}/edit"), 'label' => trans("texts.view_invoice")];
            } else {
                $actions[] = ['url' => 'javascript:onConvertClick()', 'label' => trans("texts.convert_to_invoice")];
            }
        } elseif ($entityType == ENTITY_INVOICE) {
            if ($invoice->quote_id) {
                $actions[] = ['url' => URL::to("quotes/{$invoice->quote_id}/edit"), 'label' => trans("texts.view_quote")];
            }

            if (!$invoice->is_recurring && $invoice->balance > 0) {
                $actions[] = ['url' => 'javascript:onPaymentClick()', 'label' => trans('texts.enter_payment')];
            }
        }

        if (count($actions) > 3) {
            $actions[] = DropdownButton::DIVIDER;
        }

        $actions[] = ['url' => 'javascript:onArchiveClick()', 'label' => trans("texts.archive_{$entityType}")];
        $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans("texts.delete_{$entityType}")];

        $lastSent = ($invoice->is_recurring && $invoice->last_sent_date) ? $invoice->recurring_invoices->last() : null;

        $data = array(
                'entityType' => $entityType,
                'showBreadcrumbs' => $clone,
                'invoice' => $invoice,
                'data' => false,
                'method' => $method,
                'invitationContactIds' => $contactIds,
                'url' => $url,
                'title' => trans("texts.edit_{$entityType}"),
                'client' => $invoice->client,
                'isRecurring' => $invoice->is_recurring,
                'actions' => $actions,
                'lastSent' => $lastSent);
        $data = array_merge($data, self::getViewModel());

        if ($clone) {
            $data['formIsChanged'] = true;
        }

        // Set the invitation data on the client's contacts
        if (!$clone) {
            $clients = $data['clients'];
            foreach ($clients as $client) {
                if ($client->id != $invoice->client->id) {
                    continue;
                }

                foreach ($invoice->invitations as $invitation) {
                    foreach ($client->contacts as $contact) {
                        if ($invitation->contact_id == $contact->id) {
                            $contact->email_error = $invitation->email_error;
                            $contact->invitation_link = $invitation->getLink();
                            $contact->invitation_viewed = $invitation->viewed_date && $invitation->viewed_date != '0000-00-00 00:00:00' ? $invitation->viewed_date : false;
                            $contact->invitation_status = $contact->email_error ? false : $invitation->getStatus();
                        }
                    }
                }

                break;
            }
        }

        return View::make('invoices.edit', $data);
    }

    public function create($clientPublicId = 0, $isRecurring = false)
    {
        $client = null;
        if ($clientPublicId) {
            $client = Client::scope($clientPublicId)->firstOrFail();
        }

        $invoice = Invoice::createNew();
        $invoice->client = $client;
        $invoice->is_recurring = $isRecurring;
        $invoice->initialize();
        
        $data = [
            'entityType' => $invoice->getEntityType(),
            'invoice' => $invoice,
            'data' => Input::old('data'),
            'method' => 'POST',
            'url' => 'invoices',
            'title' => trans('texts.new_invoice'),
        ];
        $data = array_merge($data, self::getViewModel());
        
        return View::make('invoices.edit', $data);
    }

    public function createRecurring($clientPublicId = 0)
    {
        return self::create($clientPublicId, true);
    }

    private static function getViewModel()
    {
        $recurringHelp = '';
        foreach (preg_split("/((\r?\n)|(\r\n?))/", trans('texts.recurring_help')) as $line) {
            $parts = explode("=>", $line);
            if (count($parts) > 1) {
                $line = $parts[0].' => '.Utils::processVariables($parts[0]);
                $recurringHelp .= '<li>'.strip_tags($line).'</li>';
            } else {
                $recurringHelp .= $line;
            }
        }

        return [
            'account' => Auth::user()->account->load('country'),
            'products' => Product::scope()->with('default_tax_rate')->orderBy('id')->get(),
            'countries' => Cache::get('countries'),
            'clients' => Client::scope()->with('contacts', 'country')->orderBy('name')->get(),
            'taxRates' => TaxRate::scope()->orderBy('name')->get(),
            'currencies' => Cache::get('currencies'),
            'languages' => Cache::get('languages'),
            'sizes' => Cache::get('sizes'),
            'paymentTerms' => Cache::get('paymentTerms'),
            'industries' => Cache::get('industries'),
            'invoiceDesigns' => InvoiceDesign::getDesigns(),
            'frequencies' => array(
                1 => 'Weekly',
                2 => 'Two weeks',
                3 => 'Four weeks',
                4 => 'Monthly',
                5 => 'Three months',
                6 => 'Six months',
                7 => 'Annually',
            ),
            'recurringHelp' => $recurringHelp,
            'invoiceLabels' => Auth::user()->account->getInvoiceLabels(),
            'tasks' => Session::get('tasks') ? json_encode(Session::get('tasks')) : null,
        ];

    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        return InvoiceController::save();
    }

    private function save($publicId = null)
    {
        $action = Input::get('action');
        $entityType = Input::get('entityType');
        $input = json_decode(Input::get('data'));

        if (in_array($action, ['archive', 'delete', 'mark', 'restore'])) {
            return InvoiceController::bulk($entityType);
        }

        if ($errors = $this->invoiceRepo->getErrors($input->invoice)) {
            Session::flash('error', trans('texts.invoice_error'));

            return Redirect::to("{$entityType}s/create")
                ->withInput()->withErrors($errors);
        } else {
            $invoice = $this->saveInvoice($publicId, $input, $entityType);
            $url = "{$entityType}s/".$invoice->public_id.'/edit';
            $message = trans($publicId ? "texts.updated_{$entityType}" : "texts.created_{$entityType}");

            // check if we created a new client with the invoice
            if ($input->invoice->client->public_id == '-1') {
                $message = $message.' '.trans('texts.and_created_client');
                $trackUrl = URL::to('clients/'.$invoice->client->public_id);
                Utils::trackViewed($invoice->client->getDisplayName(), ENTITY_CLIENT, $trackUrl);
            }

            if ($action == 'clone') {
                return $this->cloneInvoice($publicId);
            } elseif ($action == 'convert') {
                return $this->convertQuote($publicId);
            } elseif ($action == 'email') {
                return $this->emailInvoice($invoice, Input::get('pdfupload'));
            }
            
            Session::flash('message', $message);
            return Redirect::to($url);
        }
    }

    private function emailInvoice($invoice, $pdfUpload)
    {
        $entityType = $invoice->getEntityType();
        $pdfUpload = Utils::decodePDF($pdfUpload);

        if (!Auth::user()->confirmed) {
            $errorMessage = trans(Auth::user()->registered ? 'texts.confirmation_required' : 'texts.registration_required');
            Session::flash('error', $errorMessage);
            return Redirect::to('invoices/'.$invoice->public_id.'/edit');
        }

        if ($invoice->is_recurring) {
            $response = $this->emailRecurringInvoice($invoice);
        } else {
            $response = $this->mailer->sendInvoice($invoice, false, $pdfUpload);
        }

        if ($response === true) {
            $message = trans("texts.emailed_{$entityType}");
            Session::flash('message', $message);
        } else {
            Session::flash('error', $response);
        }

        return Redirect::to("{$entityType}s/{$invoice->public_id}/edit");
    }

    private function emailRecurringInvoice(&$invoice)
    {
        if (!$invoice->shouldSendToday()) {
            if ($date = $invoice->getNextSendDate()) {
                $date = $invoice->account->formatDate($date);
                $date .= ' ' . DEFAULT_SEND_RECURRING_HOUR . ':00 am ' . $invoice->account->getTimezone();
                return trans('texts.recurring_too_soon', ['date' => $date]);
            } else {
                return trans('texts.no_longer_running');
            }
        }

        // switch from the recurring invoice to the generated invoice
        $invoice = $this->invoiceRepo->createRecurringInvoice($invoice);

        // in case auto-bill is enabled then a receipt has been sent
        if ($invoice->isPaid()) {
            return true;
        } else {
            return $this->mailer->sendInvoice($invoice);
        }
    }

    private function saveInvoice($publicId, $input, $entityType)
    {
        $invoice = $input->invoice;

        $clientData = (array) $invoice->client;
        $client = $this->clientRepo->save($invoice->client->public_id, $clientData);

        $invoiceData = (array) $invoice;
        $invoiceData['client_id'] = $client->id;
        $invoice = $this->invoiceRepo->save($publicId, $invoiceData, $entityType);

        $client->load('contacts');
        $sendInvoiceIds = [];

        foreach ($client->contacts as $contact) {
            if ($contact->send_invoice || count($client->contacts) == 1) {
                $sendInvoiceIds[] = $contact->id;
            }
        }

        foreach ($client->contacts as $contact) {
            $invitation = Invitation::scope()->whereContactId($contact->id)->whereInvoiceId($invoice->id)->first();

            if (in_array($contact->id, $sendInvoiceIds) && !$invitation) {
                $invitation = Invitation::createNew();
                $invitation->invoice_id = $invoice->id;
                $invitation->contact_id = $contact->id;
                $invitation->invitation_key = str_random(RANDOM_KEY_LENGTH);
                $invitation->save();
            } elseif (!in_array($contact->id, $sendInvoiceIds) && $invitation) {
                $invitation->delete();
            }
        }

        return $invoice;
    }

    /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("invoices/{$publicId}/edit");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update($publicId)
    {
        return InvoiceController::save($publicId);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function bulk($entityType = ENTITY_INVOICE)
    {
        $action = Input::get('action');
        $statusId = Input::get('statusId', INVOICE_STATUS_SENT);
        $ids = Input::get('id') ? Input::get('id') : Input::get('ids');
        $count = $this->invoiceRepo->bulk($ids, $action, $statusId);

        if ($count > 0) {
            $key = $action == 'mark' ? "updated_{$entityType}" : "{$action}d_{$entityType}";
            $message = Utils::pluralize($key, $count);
            Session::flash('message', $message);
        }

        if ($action == 'restore' && $count == 1) {
            return Redirect::to("{$entityType}s/".Utils::getFirst($ids));
        } else {
            return Redirect::to("{$entityType}s");
        }
    }

    public function convertQuote($publicId)
    {
        $invoice = Invoice::with('invoice_items')->scope($publicId)->firstOrFail();
        $clone = $this->invoiceRepo->cloneInvoice($invoice, $invoice->id);

        Session::flash('message', trans('texts.converted_to_invoice'));
        return Redirect::to('invoices/'.$clone->public_id);
    }

    public function cloneInvoice($publicId)
    {
        /*
        $invoice = Invoice::with('invoice_items')->scope($publicId)->firstOrFail();
        $clone = $this->invoiceRepo->cloneInvoice($invoice);
        $entityType = $invoice->getEntityType();

        Session::flash('message', trans('texts.cloned_invoice'));
        return Redirect::to("{$entityType}s/" . $clone->public_id);
        */

        return self::edit($publicId, true);
    }

    public function invoiceHistory($publicId)
    {
        $invoice = Invoice::withTrashed()->scope($publicId)->firstOrFail();
        $invoice->load('user', 'invoice_items', 'account.country', 'client.contacts', 'client.country');
        $invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
        $invoice->due_date = Utils::fromSqlDate($invoice->due_date);
        $invoice->is_pro = Auth::user()->isPro();
        $invoice->is_quote = intval($invoice->is_quote);

        $activityTypeId = $invoice->is_quote ? ACTIVITY_TYPE_UPDATE_QUOTE : ACTIVITY_TYPE_UPDATE_INVOICE;
        $activities = Activity::scope(false, $invoice->account_id)
                        ->where('activity_type_id', '=', $activityTypeId)
                        ->where('invoice_id', '=', $invoice->id)
                        ->orderBy('id', 'desc')
                        ->get(['id', 'created_at', 'user_id', 'json_backup', 'message']);

        $versionsJson = [];
        $versionsSelect = [];
        $lastId = false;

        foreach ($activities as $activity) {
            $backup = json_decode($activity->json_backup);
            $backup->invoice_date = Utils::fromSqlDate($backup->invoice_date);
            $backup->due_date = Utils::fromSqlDate($backup->due_date);
            $backup->is_pro = Auth::user()->isPro();
            $backup->is_quote = isset($backup->is_quote) && intval($backup->is_quote);
            $backup->account = $invoice->account->toArray();

            $versionsJson[$activity->id] = $backup;
            $key = Utils::timestampToDateTimeString(strtotime($activity->created_at)) . ' - ' . Utils::decodeActivity($activity->message);
            $versionsSelect[$lastId ? $lastId : 0] = $key;
            $lastId = $activity->id;
        }

        $versionsSelect[$lastId] = Utils::timestampToDateTimeString(strtotime($invoice->created_at)) . ' - ' . $invoice->user->getDisplayName();

        $data = [
            'invoice' => $invoice,
            'versionsJson' => json_encode($versionsJson),
            'versionsSelect' => $versionsSelect,
            'invoiceDesigns' => InvoiceDesign::getDesigns(),
        ];

        return View::make('invoices.history', $data);
    }
}
