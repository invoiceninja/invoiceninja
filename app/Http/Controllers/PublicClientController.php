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

        $data = array(
            'account' => $account,
            'showApprove' => $showApprove,
            'showBreadcrumbs' => false,
            'hideLogo' => $account->hasFeature(FEATURE_WHITE_LABEL),
            'hideHeader' => $account->isNinjaAccount() || !$account->enable_client_portal,
            'hideDashboard' => !$account->enable_client_portal_dashboard,
            'showDocuments' => $account->hasFeature(FEATURE_DOCUMENTS),
            'clientViewCSS' => $account->clientViewCSS(),
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
            'hideLogo' => $account->hasFeature(FEATURE_WHITE_LABEL),
            'showDocuments' => $account->hasFeature(FEATURE_DOCUMENTS),
            'clientViewCSS' => $account->clientViewCSS(),
            'clientFontUrl' => $account->getFontsUrl(),
        ];
        
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
                ];

                return trans("texts.activity_{$model->activity_type_id}", $data);
             })
            ->addColumn('balance', function ($model) { return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id); })
            ->addColumn('adjustment', function ($model) { return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id, $model->country_id) : ''; })
            ->make();
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
            'hideLogo' => $account->hasFeature(FEATURE_WHITE_LABEL),
            'hideDashboard' => !$account->enable_client_portal_dashboard,
            'showDocuments' => $account->hasFeature(FEATURE_DOCUMENTS),
            'clientViewCSS' => $account->clientViewCSS(),
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
            'hideLogo' => $account->hasFeature(FEATURE_WHITE_LABEL),
            'hideDashboard' => !$account->enable_client_portal_dashboard,
            'showDocuments' => $account->hasFeature(FEATURE_DOCUMENTS),
            'clientViewCSS' => $account->clientViewCSS(),
            'clientFontUrl' => $account->getFontsUrl(),
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'columns' => Utils::trans(['invoice', 'transaction_reference', 'method', 'payment_amount', 'payment_date'])
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
                ->addColumn('payment_type', function ($model) { return $model->payment_type ? $model->payment_type : ($model->account_gateway_id ? '<i>Online payment</i>' : ''); })
                ->addColumn('amount', function ($model) { return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id); })
                ->addColumn('payment_date', function ($model) { return Utils::dateToString($model->payment_date); })
                ->make();
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
          'hideLogo' => $account->hasFeature(FEATURE_WHITE_LABEL),
          'hideDashboard' => !$account->enable_client_portal_dashboard,
          'showDocuments' => $account->hasFeature(FEATURE_DOCUMENTS),
          'clientViewCSS' => $account->clientViewCSS(),
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
          'hideLogo' => $account->hasFeature(FEATURE_WHITE_LABEL),
          'hideDashboard' => !$account->enable_client_portal_dashboard,
          'showDocuments' => $account->hasFeature(FEATURE_DOCUMENTS),
          'clientViewCSS' => $account->clientViewCSS(),
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

}
