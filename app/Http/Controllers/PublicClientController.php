<?php namespace App\Http\Controllers;

use Auth;
use View;
use DB;
use URL;
use Input;
use Utils;
use Request;
use Response;
use Session;
use Datatable;
use Validator;
use Cache;
use Redirect;
use App\Models\Gateway;
use App\Models\Invitation;
use App\Models\Document;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\ActivityRepository;
use App\Ninja\Repositories\DocumentRepository;
use App\Events\InvoiceInvitationWasViewed;
use App\Events\QuoteInvitationWasViewed;
use App\Services\PaymentService;
use Barracuda\ArchiveStream\ZipArchive;

class PublicClientController extends BaseController
{
    private $invoiceRepo;
    private $paymentRepo;
    private $documentRepo;

    public function __construct(InvoiceRepository $invoiceRepo, PaymentRepository $paymentRepo, ActivityRepository $activityRepo, DocumentRepository $documentRepo, PaymentService $paymentService)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->activityRepo = $activityRepo;
        $this->documentRepo = $documentRepo;
        $this->paymentService = $paymentService;
    }

    public function view($invitationKey)
    {
        if (!$invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return $this->returnError();
        }

        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $invoice->account;

        if (!$account->checkSubdomain(Request::server('HTTP_HOST'))) {
            return response()->view('error', [
                'error' => trans('texts.invoice_not_found'),
                'hideHeader' => true,
                'clientViewCSS' => $account->clientViewCSS(),
                'clientFontUrl' => $account->getFontsUrl(),
            ]);
        }

        if (!Input::has('phantomjs') && !Input::has('silent') && !Session::has($invitationKey) 
            && (!Auth::check() || Auth::user()->account_id != $invoice->account_id)) {
            if ($invoice->is_quote) {
                event(new QuoteInvitationWasViewed($invoice, $invitation));
            } else {
                event(new InvoiceInvitationWasViewed($invoice, $invitation));
            }
        }

        Session::put($invitationKey, true); // track this invitation has been seen
        Session::put('invitation_key', $invitationKey); // track current invitation

        $account->loadLocalizationSettings($client);
        
        $invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
        $invoice->due_date = Utils::fromSqlDate($invoice->due_date);
        $invoice->features = [
            'customize_invoice_design' => $account->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN),
            'remove_created_by' => $account->hasFeature(FEATURE_REMOVE_CREATED_BY),
            'invoice_settings' => $account->hasFeature(FEATURE_INVOICE_SETTINGS),
        ];
        $invoice->invoice_fonts = $account->getFontsData();
        
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

        $data = array();
        $paymentTypes = $this->getPaymentTypes($client, $invitation);
        $paymentURL = '';
        if (count($paymentTypes) == 1) {
            $paymentURL = $paymentTypes[0]['url'];
            if (!$account->isGatewayConfigured(GATEWAY_PAYPAL_EXPRESS)) {
                $paymentURL = URL::to($paymentURL);
            }
        }

        if ($braintreeGateway = $account->getGatewayConfig(GATEWAY_BRAINTREE)){
            if($braintreeGateway->getPayPalEnabled()) {
                $data['braintreeClientToken'] = $this->paymentService->getBraintreeClientToken($account);
            }
        }

        $showApprove = $invoice->quote_invoice_id ? false : true;
        if ($invoice->due_date) {
            $showApprove = time() < strtotime($invoice->due_date);
        }
        if ($invoice->invoice_status_id >= INVOICE_STATUS_APPROVED) {
            $showApprove = false;
        }

        // Checkout.com requires first getting a payment token
        $checkoutComToken = false;
        $checkoutComKey = false;
        $checkoutComDebug = false;
        if ($accountGateway = $account->getGatewayConfig(GATEWAY_CHECKOUT_COM)) {
            $checkoutComDebug = $accountGateway->getConfigField('testMode');
            if ($checkoutComToken = $this->paymentService->getCheckoutComToken($invitation)) {
                $checkoutComKey = $accountGateway->getConfigField('publicApiKey');
                $invitation->transaction_reference = $checkoutComToken;
                $invitation->save();
            }
        }

        $data += array(
            'account' => $account,
            'showApprove' => $showApprove,
            'showBreadcrumbs' => false,
            'clientFontUrl' => $account->getFontsUrl(),
            'invoice' => $invoice->hidePrivateFields(),
            'invitation' => $invitation,
            'invoiceLabels' => $account->getInvoiceLabels(),
            'contact' => $contact,
            'paymentTypes' => $paymentTypes,
            'paymentURL' => $paymentURL,
            'checkoutComToken' => $checkoutComToken,
            'checkoutComKey' => $checkoutComKey,
            'checkoutComDebug' => $checkoutComDebug,
            'phantomjs' => Input::has('phantomjs'),
        );
        
        if($account->hasFeature(FEATURE_DOCUMENTS) && $this->canCreateZip()){
            $zipDocs = $this->getInvoiceZipDocuments($invoice, $size);
            
            if(count($zipDocs) > 1){
                $data['documentsZipURL'] = URL::to("client/documents/{$invitation->invitation_key}");
                $data['documentsZipSize'] = $size;
            }
        }

        return View::make('invoices.view', $data);
    }

    private function getPaymentTypes($client, $invitation)
    {
        $paymentTypes = [];
        $account = $client->account;

        $paymentMethods = $this->paymentService->getClientPaymentMethods($client);

        if ($paymentMethods) {
            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod['type']->id != PAYMENT_TYPE_ACH || $paymentMethod['status'] == 'verified') {

                    if ($paymentMethod['type']->id == PAYMENT_TYPE_ACH) {
                        $html = '<div>'.htmlentities($paymentMethod['bank_name']).'</div>';
                    } elseif ($paymentMethod['type']->id == PAYMENT_TYPE_ID_PAYPAL) {
                        $html = '<img height="22" src="'.URL::to('/images/credit_cards/paypal.png').'" alt="'.trans("texts.card_".$code).'">';
                    } else {
                        $code = htmlentities(str_replace(' ', '', strtolower($paymentMethod['type']->name)));
                        $html = '<img height="22" src="'.URL::to('/images/credit_cards/'.$code.'.png').'" alt="'.trans("texts.card_".$code).'">';
                    }

                    $url = URL::to("/payment/{$invitation->invitation_key}/token/".$paymentMethod['id']);

                    if ($paymentMethod['type']->id == PAYMENT_TYPE_ID_PAYPAL) {
                        $html .= '&nbsp;&nbsp;<span>'.$paymentMethod['email'].'</span>';
                        $url .= '#braintree_paypal';
                    } elseif ($paymentMethod['type']->id != PAYMENT_TYPE_ACH) {
                        $html .= '<div class="pull-right" style="text-align:right">'.trans('texts.card_expiration', array('expires' => Utils::fromSqlDate($paymentMethod['expiration'], false)->format('m/y'))).'<br>';
                        $html .= '&bull;&bull;&bull;'.$paymentMethod['last4'].'</div>';
                    } else {
                        $html .= '<div style="text-align:right">';
                        $html .= '&bull;&bull;&bull;'.$paymentMethod['last4'].'</div>';
                    }

                    $paymentTypes[] = [
                        'url' => $url,
                        'label' => $html,
                    ];
                }
            }
        }


        foreach(Gateway::$paymentTypes as $type) {
            if ($gateway = $account->getGatewayByType($type)) {
                $types = array($type);

                if ($type == PAYMENT_TYPE_STRIPE) {
                    $types = array(PAYMENT_TYPE_STRIPE_CREDIT_CARD);
                    if ($gateway->getAchEnabled()) {
                        $types[] = PAYMENT_TYPE_STRIPE_ACH;
                    }
                }

                foreach($types as $type) {
                    $typeLink = strtolower(str_replace('PAYMENT_TYPE_', '', $type));
                    $url = URL::to("/payment/{$invitation->invitation_key}/{$typeLink}");

                    // PayPal doesn't allow being run in an iframe so we need to open in new tab
                    if ($type === PAYMENT_TYPE_PAYPAL && $account->iframe_url) {
                        $url = 'javascript:window.open("' . $url . '", "_blank")';
                    }

                    if ($type == PAYMENT_TYPE_STRIPE_CREDIT_CARD) {
                        $label = trans('texts.' . strtolower(PAYMENT_TYPE_CREDIT_CARD));
                    } elseif ($type == PAYMENT_TYPE_STRIPE_ACH) {
                        $label = trans('texts.' . strtolower(PAYMENT_TYPE_DIRECT_DEBIT));
                    } else {
                        $label = trans('texts.' . strtolower($type));
                    }

                    $paymentTypes[] = [
                        'url' => $url, 'label' => $label
                    ];

                    if($gateway->getPayPalEnabled()) {
                        $paymentTypes[] = [
                            'label' => trans('texts.paypal'),
                            'url' => $url = URL::to("/payment/{$invitation->invitation_key}/braintree_paypal"),
                        ];
                    }
                }
            }
        }

        return $paymentTypes;
    }

    public function download($invitationKey)
    {
        if (!$invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return response()->view('error', [
                'error' => trans('texts.invoice_not_found'),
                'hideHeader' => true,
            ]);
        }

        $invoice = $invitation->invoice;
        $pdfString = $invoice->getPDFString();

        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdfString));
        header('Content-disposition: attachment; filename="' . $invoice->getFileName() . '"');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        return $pdfString;
    }

    public function dashboard()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $account = $invitation->account;
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        if (!$account->enable_client_portal || !$account->enable_client_portal_dashboard) {
            return $this->returnError();
        }

        $data = [
            'color' => $color,
            'account' => $account,
            'client' => $client,
            'clientFontUrl' => $account->getFontsUrl(),
            'gateway' => $account->getTokenGateway(),
            'paymentMethods' => $this->paymentService->getClientPaymentMethods($client),
        ];

        if ($braintreeGateway = $account->getGatewayConfig(GATEWAY_BRAINTREE)){
            if($braintreeGateway->getPayPalEnabled()) {
                $data['braintreeClientToken'] = $this->paymentService->getBraintreeClientToken($account);
            }
        }
        
        return response()->view('invited.dashboard', $data);
    }

    public function activityDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return false;
        }
        $invoice = $invitation->invoice;

        $query = $this->activityRepo->findByClientId($invoice->client_id);
        $query->where('activities.adjustment', '!=', 0);

        return Datatable::query($query)
            ->addColumn('activities.id', function ($model) { return Utils::timestampToDateTimeString(strtotime($model->created_at)); })
            ->addColumn('activity_type_id', function ($model) {
                $data = [
                    'client' => Utils::getClientDisplayName($model),
                    'user' => $model->is_system ? ('<i>' . trans('texts.system') . '</i>') : ($model->user_first_name . ' ' . $model->user_last_name),
                    'invoice' => trans('texts.invoice') . ' ' . $model->invoice,
                    'contact' => Utils::getClientDisplayName($model),
                    'payment' => trans('texts.payment') . ($model->payment ? ' ' . $model->payment : ''),
                    'credit' => $model->payment_amount ? Utils::formatMoney($model->credit, $model->currency_id, $model->country_id) : '',
                    'payment_amount' => $model->payment_amount ? Utils::formatMoney($model->payment_amount, $model->currency_id, $model->country_id) : null,
                    'adjustment' => $model->adjustment ? Utils::formatMoney($model->adjustment, $model->currency_id, $model->country_id) : null,
                ];

                return trans("texts.activity_{$model->activity_type_id}", $data);
             })
            ->addColumn('balance', function ($model) { return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id); })
            ->addColumn('adjustment', function ($model) { return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id, $model->country_id) : ''; })
            ->make();
    }

    public function recurringInvoiceIndex()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $account = $invitation->account;

        if (!$account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        
        $data = [
            'color' => $color,
            'account' => $account,
            'client' => $invitation->invoice->client,
            'clientFontUrl' => $account->getFontsUrl(),
            'title' => trans('texts.recurring_invoices'),
            'entityType' => ENTITY_RECURRING_INVOICE,
            'columns' => Utils::trans(['frequency', 'start_date', 'end_date', 'invoice_total', 'auto_bill']),
        ];

        return response()->view('public_list', $data);
    }

    public function invoiceIndex()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $account = $invitation->account;

        if (!$account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
            'color' => $color,
            'account' => $account,
            'client' => $invitation->invoice->client,
            'clientFontUrl' => $account->getFontsUrl(),
            'title' => trans('texts.invoices'),
            'entityType' => ENTITY_INVOICE,
            'columns' => Utils::trans(['invoice_number', 'invoice_date', 'invoice_total', 'balance_due', 'due_date']),
        ];

        return response()->view('public_list', $data);
    }

    public function invoiceDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return '';
        }

        return $this->invoiceRepo->getClientDatatable($invitation->contact_id, ENTITY_INVOICE, Input::get('sSearch'));
    }

    public function recurringInvoiceDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return '';
        }

        return $this->invoiceRepo->getClientRecurringDatatable($invitation->contact_id);
    }


    public function paymentIndex()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }
        $account = $invitation->account;

        if (!$account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';        
        $data = [
            'color' => $color,
            'account' => $account,
            'clientFontUrl' => $account->getFontsUrl(),
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'columns' => Utils::trans(['invoice', 'transaction_reference', 'method', 'source', 'payment_amount', 'payment_date', 'status'])
        ];

        return response()->view('public_list', $data);
    }

    public function paymentDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return false;
        }
        $payments = $this->paymentRepo->findForContact($invitation->contact->id, Input::get('sSearch'));

        return Datatable::query($payments)
                ->addColumn('invoice_number', function ($model) { return $model->invitation_key ? link_to('/view/'.$model->invitation_key, $model->invoice_number)->toHtml() : $model->invoice_number; })
                ->addColumn('transaction_reference', function ($model) { return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>'; })
                ->addColumn('payment_type', function ($model) { return ($model->payment_type && !$model->last4) ? $model->payment_type : ($model->account_gateway_id ? '<i>Online payment</i>' : ''); })
                ->addColumn('payment_source', function ($model) {
                    $code = str_replace(' ', '', strtolower($model->payment_type));
                    $card_type = trans("texts.card_" . $code);
                    if ($model->payment_type_id != PAYMENT_TYPE_ACH) {
                        if($model->last4) {
                            $expiration = trans('texts.card_expiration', array('expires' => Utils::fromSqlDate($model->expiration, false)->format('m/y')));
                            return '<img height="22" src="' . URL::to('/images/credit_cards/' . $code . '.png') . '" alt="' . htmlentities($card_type) . '">&nbsp; &bull;&bull;&bull;' . $model->last4 . ' ' . $expiration;
                        } elseif ($model->email) {
                            return $model->email;
                        }
                    } elseif ($model->last4) {
                        $bankData = PaymentController::getBankData($model->routing_number);
                        if (is_array($bankData)) {
                            return $bankData['name'].'&nbsp; &bull;&bull;&bull;' . $model->last4;
                        } elseif($model->last4) {
                            return '<img height="22" src="' . URL::to('/images/credit_cards/ach.png') . '" alt="' . htmlentities($card_type) . '">&nbsp; &bull;&bull;&bull;' . $model->last4;
                        }
                    }
                })
                ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id); })
                ->addColumn('payment_date', function ($model) { return Utils::dateToString($model->payment_date); })
                ->addColumn('status', function ($model) { return $this->getPaymentStatusLabel($model); })
                ->orderColumns( 'invoice_number', 'transaction_reference', 'payment_type', 'amount', 'payment_date')
                ->make();
    }
    
    private function getPaymentStatusLabel($model)
    {
        $label = trans("texts.status_" . strtolower($model->payment_status_name));
        $class = 'default';
        switch ($model->payment_status_id) {
            case PAYMENT_STATUS_PENDING:
                $class = 'info';
                break;
            case PAYMENT_STATUS_COMPLETED:
                $class = 'success';
                break;
            case PAYMENT_STATUS_FAILED:
                $class = 'danger';
                break;
            case PAYMENT_STATUS_PARTIALLY_REFUNDED:
                $label = trans('texts.status_partially_refunded_amount', [
                    'amount' => Utils::formatMoney($model->refunded, $model->currency_id, $model->country_id),
                ]);
                $class = 'primary';
                break;
            case PAYMENT_STATUS_REFUNDED:
                $class = 'default';
                break;
        }
        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }

    public function quoteIndex()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $account = $invitation->account;

        if (!$account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        $data = [
          'color' => $color,
          'account' => $account,
          'clientFontUrl' => $account->getFontsUrl(),
          'title' => trans('texts.quotes'),
          'entityType' => ENTITY_QUOTE,
          'columns' => Utils::trans(['quote_number', 'quote_date', 'quote_total', 'due_date']),
        ];

        return response()->view('public_list', $data);
    }


    public function quoteDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return false;
        }

        return $this->invoiceRepo->getClientDatatable($invitation->contact_id, ENTITY_QUOTE, Input::get('sSearch'));
    }

    public function documentIndex()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $account = $invitation->account;

        if (!$account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';        
        $data = [
          'color' => $color,
          'account' => $account,
          'clientFontUrl' => $account->getFontsUrl(),
          'title' => trans('texts.documents'),
          'entityType' => ENTITY_DOCUMENT,
          'columns' => Utils::trans(['invoice_number', 'name', 'document_date', 'document_size']),
        ];

        return response()->view('public_list', $data);
    }


    public function documentDatatable()
    {
        if (!$invitation = $this->getInvitation()) {
            return false;
        }

        return $this->documentRepo->getClientDatatable($invitation->contact_id, ENTITY_DOCUMENT, Input::get('sSearch'));
    }

    private function returnError($error = false)
    {
        return response()->view('error', [
            'error' => $error ?: trans('texts.invoice_not_found'),
            'hideHeader' => true,
        ]);
    }

    private function getInvitation()
    {
        $invitationKey = session('invitation_key');

        if (!$invitationKey) {
            return false;
        }

        $invitation = Invitation::where('invitation_key', '=', $invitationKey)->first();

        if (!$invitation || $invitation->is_deleted) {
            return false;
        }

        $invoice = $invitation->invoice;

        if (!$invoice || $invoice->is_deleted) {
            return false;
        }

        return $invitation;
    }
        
    public function getDocumentVFSJS($publicId, $name){
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }
        
        $clientId = $invitation->invoice->client_id;
        $document = Document::scope($publicId, $invitation->account_id)->first();
        
                
        if(!$document->isPDFEmbeddable()){
            return Response::view('error', array('error'=>'Image does not exist!'), 404);
        }
        
        $authorized = false;
        if($document->expense && $document->expense->client_id == $invitation->invoice->client_id){
            $authorized = true;
        } else if($document->invoice && $document->invoice->client_id == $invitation->invoice->client_id){
            $authorized = true;
        }
        
        if(!$authorized){
            return Response::view('error', array('error'=>'Not authorized'), 403);
        }        
        
        if(substr($name, -3)=='.js'){
            $name = substr($name, 0, -3);
        }
        
        $content = $document->preview?$document->getRawPreview():$document->getRaw();
        $content = 'ninjaAddVFSDoc('.json_encode(intval($publicId).'/'.strval($name)).',"'.base64_encode($content).'")';
        $response = Response::make($content, 200);
        $response->header('content-type', 'text/javascript');
        $response->header('cache-control', 'max-age=31536000');
        
        return $response;
    }
    
    protected function canCreateZip(){
        return function_exists('gmp_init');
    }
    
    protected function getInvoiceZipDocuments($invoice, &$size=0){
        $documents = $invoice->documents;
        
        foreach($invoice->expenses as $expense){
            $documents = $documents->merge($expense->documents);
        }
        
        $documents = $documents->sortBy('size');

        $size = 0;
        $maxSize = MAX_ZIP_DOCUMENTS_SIZE * 1000;
        $toZip = array();
        foreach($documents as $document){
            if($size + $document->size > $maxSize)break;
            
            if(!empty($toZip[$document->name])){
                // This name is taken
                if($toZip[$document->name]->hash != $document->hash){
                    // 2 different files with the same name
                    $nameInfo = pathinfo($document->name);
                    
                    for($i = 1;; $i++){
                        $name = $nameInfo['filename'].' ('.$i.').'.$nameInfo['extension'];
                        
                        if(empty($toZip[$name])){
                            $toZip[$name] = $document;
                            $size += $document->size;
                            break;
                        } else if ($toZip[$name]->hash == $document->hash){
                            // We're not adding this after all
                            break;
                        }
                    }
                    
                }
            }
            else{
                $toZip[$document->name] = $document;
                $size += $document->size;
            }
        }
        
        return $toZip;
    }
    
    public function getInvoiceDocumentsZip($invitationKey){
        if (!$invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return $this->returnError();
        }
        
        Session::put('invitation_key', $invitationKey); // track current invitation
        
        $invoice = $invitation->invoice;
        
        $toZip = $this->getInvoiceZipDocuments($invoice);
        
        if(!count($toZip)){
            return Response::view('error', array('error'=>'No documents small enough'), 404);
        }
        
        $zip = new ZipArchive($invitation->account->name.' Invoice '.$invoice->invoice_number.'.zip');
        return Response::stream(function() use ($toZip, $zip) {
            foreach($toZip as $name=>$document){
                $fileStream = $document->getStream();
                if($fileStream){
                    $zip->init_file_stream_transfer($name, $document->size, array('time'=>$document->created_at->timestamp));
                    while ($buffer = fread($fileStream, 256000))$zip->stream_file_part($buffer);
                    fclose($fileStream);
                    $zip->complete_file_stream();
                }
                else{
                    $zip->add_file($name, $document->getRaw());
                }
            }
            $zip->finish();
        }, 200);
    }
    
    public function getDocument($invitationKey, $publicId){
        if (!$invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return $this->returnError();
        }
        
        Session::put('invitation_key', $invitationKey); // track current invitation
        
        $clientId = $invitation->invoice->client_id;
        $document = Document::scope($publicId, $invitation->account_id)->firstOrFail();
        
        $authorized = false;
        if($document->expense && $document->expense->client_id == $invitation->invoice->client_id){
            $authorized = true;
        } else if($document->invoice && $document->invoice->client_id == $invitation->invoice->client_id){
            $authorized = true;
        }
        
        if(!$authorized){
            return Response::view('error', array('error'=>'Not authorized'), 403);
        }        
        
        return DocumentController::getDownloadResponse($document);
    }

    public function paymentMethods()
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $client = $invitation->invoice->client;
        $account = $client->account;
        $paymentMethods = $this->paymentService->getClientPaymentMethods($client);

        $data = array(
            'account' => $account,
            'color' => $account->primary_color ? $account->primary_color : '#0b4d78',
            'client' => $client,
            'clientViewCSS' => $account->clientViewCSS(),
            'clientFontUrl' => $account->getFontsUrl(),
            'paymentMethods' => $paymentMethods,
            'gateway' => $account->getTokenGateway(),
            'title' => trans('texts.payment_methods')
        );

        if ($braintreeGateway = $account->getGatewayConfig(GATEWAY_BRAINTREE)){
            if($braintreeGateway->getPayPalEnabled()) {
                $data['braintreeClientToken'] = $this->paymentService->getBraintreeClientToken($account);
            }
        }

        return response()->view('payments.paymentmethods', $data);
    }

    public function verifyPaymentMethod()
    {
        $sourceId = Input::get('source_id');
        $amount1 = Input::get('verification1');
        $amount2 = Input::get('verification2');

        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $client = $invitation->invoice->client;
        $result = $this->paymentService->verifyClientPaymentMethod($client, $sourceId, $amount1, $amount2);

        if (is_string($result)) {
            Session::flash('error', $result);
        } else {
            Session::flash('message', trans('texts.payment_method_verified'));
        }

        return redirect()->to($client->account->enable_client_portal?'/client/dashboard':'/client/paymentmethods/');
    }

    public function removePaymentMethod($sourceId)
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $client = $invitation->invoice->client;
        $result = $this->paymentService->removeClientPaymentMethod($client, $sourceId);

        if (is_string($result)) {
            Session::flash('error', $result);
        } else {
            Session::flash('message', trans('texts.payment_method_removed'));
        }

        return redirect()->to($client->account->enable_client_portal?'/client/dashboard':'/client/paymentmethods/');
    }

    public function addPaymentMethod($paymentType, $token=false)
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $invoice = $invitation->invoice;
        $client = $invitation->invoice->client;
        $account = $client->account;

        $typeLink = $paymentType;
        $paymentType = 'PAYMENT_TYPE_' . strtoupper($paymentType);
        $accountGateway = $invoice->client->account->getTokenGateway();
        $gateway = $accountGateway->gateway;

        if ($token && $paymentType == PAYMENT_TYPE_BRAINTREE_PAYPAL) {
            $sourceId = $this->paymentService->createToken($this->paymentService->createGateway($accountGateway), array('token'=>$token), $accountGateway, $client, $invitation->contact_id);

            if(empty($sourceId)) {
                $this->paymentMethodError('Token-No-Ref', $this->paymentService->lastError, $accountGateway);
            } else {
                Session::flash('message', trans('texts.payment_method_added'));
            }
            return redirect()->to($account->enable_client_portal?'/client/dashboard':'/client/paymentmethods/');
        }

        $acceptedCreditCardTypes = $accountGateway->getCreditcardTypes();


        $data = [
            'showBreadcrumbs' => false,
            'client' => $client,
            'contact' => $invitation->contact,
            'gateway' => $gateway,
            'accountGateway' => $accountGateway,
            'acceptedCreditCardTypes' => $acceptedCreditCardTypes,
            'paymentType' => $paymentType,
            'countries' => Cache::get('countries'),
            'currencyId' => $client->getCurrencyId(),
            'currencyCode' => $client->currency ? $client->currency->code : ($account->currency ? $account->currency->code : 'USD'),
            'account' => $account,
            'url' => URL::to('client/paymentmethods/add/'.$typeLink),
            'clientFontUrl' => $account->getFontsUrl(),
            'showAddress' => $accountGateway->show_address,
            'paymentTitle' => trans('texts.add_payment_method'),
        ];

        if ($paymentType == PAYMENT_TYPE_STRIPE_ACH) {

            $data['currencies'] = Cache::get('currencies');
        }

        if ($gateway->id == GATEWAY_BRAINTREE) {
            $data['braintreeClientToken'] = $this->paymentService->getBraintreeClientToken($account);
        }

        return View::make('payments.add_paymentmethod', $data);
    }

    public function postAddPaymentMethod($paymentType)
    {
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $typeLink = $paymentType;
        $paymentType = 'PAYMENT_TYPE_' . strtoupper($paymentType);
        $client = $invitation->invoice->client;
        $account = $client->account;

        $accountGateway = $account->getGatewayByType($paymentType);
        $sourceToken = $accountGateway->gateway_id == GATEWAY_STRIPE ? Input::get('stripeToken'):Input::get('payment_method_nonce');

        if ($sourceToken) {
            $details = array('token' => $sourceToken);
        } elseif (Input::get('plaidPublicToken')) {
            $usingPlaid = true;
            $details = array('plaidPublicToken' => Input::get('plaidPublicToken'), 'plaidAccountId' => Input::get('plaidAccountId'));
        }

        if ($paymentType == PAYMENT_TYPE_STRIPE_ACH && !Input::get('authorize_ach')) {
            Session::flash('error', trans('texts.ach_authorization_required'));
            return Redirect::to('client/paymentmethods/add/' . $typeLink)->withInput(Request::except('cvv'));
        }

        if (!empty($details)) {
            $gateway = $this->paymentService->createGateway($accountGateway);
            $sourceId = $this->paymentService->createToken($gateway, $details, $accountGateway, $client, $invitation->contact_id);
        } else {
            return Redirect::to('client/paymentmethods/add/' . $typeLink)->withInput(Request::except('cvv'));
        }

        if(empty($sourceId)) {
            $this->paymentMethodError('Token-No-Ref', $this->paymentService->lastError, $accountGateway);
            return Redirect::to('client/paymentmethods/add/' . $typeLink)->withInput(Request::except('cvv'));
        } else if ($paymentType == PAYMENT_TYPE_STRIPE_ACH && empty($usingPlaid) ) {
            // The user needs to complete verification
            Session::flash('message', trans('texts.bank_account_verification_next_steps'));
            return Redirect::to($account->enable_client_portal?'/client/dashboard':'/client/paymentmethods/');
        } else {
            Session::flash('message', trans('texts.payment_method_added'));
            return redirect()->to($account->enable_client_portal?'/client/dashboard':'/client/paymentmethods/');
        }
    }

    public function setDefaultPaymentMethod(){
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $validator = Validator::make(Input::all(), array('source' => 'required'));
        $client = $invitation->invoice->client;
        if ($validator->fails()) {
            return Redirect::to($client->account->enable_client_portal?'/client/dashboard':'/client/paymentmethods/');
        }

        $result = $this->paymentService->setClientDefaultPaymentMethod($client, Input::get('source'));

        if (is_string($result)) {
            Session::flash('error', $result);
        } else {
            Session::flash('message', trans('texts.payment_method_set_as_default'));
        }

        return redirect()->to($client->account->enable_client_portal?'/client/dashboard':'/client/paymentmethods/');
    }

    private function paymentMethodError($type, $error, $accountGateway = false, $exception = false)
    {
        $message = '';
        if ($accountGateway && $accountGateway->gateway) {
            $message = $accountGateway->gateway->name . ': ';
        }
        $message .= $error ?: trans('texts.payment_method_error');

        Session::flash('error', $message);
        Utils::logError("Payment Method Error [{$type}]: " . ($exception ? Utils::getErrorString($exception) : $message), 'PHP', true);
    }

    public function setAutoBill(){
        if (!$invitation = $this->getInvitation()) {
            return $this->returnError();
        }

        $validator = Validator::make(Input::all(), array('public_id' => 'required'));
        $client = $invitation->invoice->client;

        if ($validator->fails()) {
            return Redirect::to('client/invoices/recurring');
        }

        $publicId = Input::get('public_id');
        $enable = Input::get('enable');
        $invoice = $client->invoices->where('public_id', intval($publicId))->first();

        if ($invoice && $invoice->is_recurring && $invoice->enable_auto_bill > AUTO_BILL_OFF) {
            $invoice->auto_bill = $enable ? true : false;
            $invoice->save();
        }

        return Redirect::to('client/invoices/recurring');
    }
}
