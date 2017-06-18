<?php

namespace App\Http\Controllers;

use App\Events\InvoiceInvitationWasViewed;
use App\Events\QuoteInvitationWasViewed;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Gateway;
use App\Models\Invitation;
use App\Models\PaymentMethod;
use App\Ninja\Repositories\ActivityRepository;
use App\Ninja\Repositories\CreditRepository;
use App\Ninja\Repositories\DocumentRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\PaymentRepository;
use App\Services\PaymentService;
use Auth;
use Barracuda\ArchiveStream\ZipArchive;
use Cache;
use Datatable;
use Exception;
use Input;
use Redirect;
use Request;
use Response;
use Session;
use URL;
use Utils;
use Validator;
use View;

class ClientPortalController extends BaseController
{
    private $invoiceRepo;
    private $paymentRepo;
    private $documentRepo;

    public function __construct(InvoiceRepository $invoiceRepo, PaymentRepository $paymentRepo, ActivityRepository $activityRepo, DocumentRepository $documentRepo, PaymentService $paymentService, CreditRepository $creditRepo)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->paymentRepo = $paymentRepo;
        $this->activityRepo = $activityRepo;
        $this->documentRepo = $documentRepo;
        $this->paymentService = $paymentService;
        $this->creditRepo = $creditRepo;
    }

    public function view($invitationKey)
    {
        if (! $invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return $this->returnError();
        }

        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $invoice->account;

        if (request()->silent) {
            session(['silent:' . $client->id => true]);
            return redirect(request()->url());
        }

        if (! $account->checkSubdomain(Request::server('HTTP_HOST'))) {
            return response()->view('error', [
                'error' => trans('texts.invoice_not_found'),
            ]);
        }

        $account->loadLocalizationSettings($client);
        $this->invoiceRepo->clearGatewayFee($invoice);

        if (! Input::has('phantomjs') && ! session('silent:' . $client->id) && ! Session::has($invitation->invitation_key)
            && (! Auth::check() || Auth::user()->account_id != $invoice->account_id)) {
            if ($invoice->isType(INVOICE_TYPE_QUOTE)) {
                event(new QuoteInvitationWasViewed($invoice, $invitation));
            } else {
                event(new InvoiceInvitationWasViewed($invoice, $invitation));
            }
        }

        Session::put($invitation->invitation_key, true); // track this invitation has been seen
        Session::put('contact_key', $invitation->contact->contact_key); // track current contact

        $invoice->invoice_date = Utils::fromSqlDate($invoice->invoice_date);
        $invoice->due_date = Utils::fromSqlDate($invoice->due_date);
        $invoice->features = [
            'customize_invoice_design' => $account->hasFeature(FEATURE_CUSTOMIZE_INVOICE_DESIGN),
            'remove_created_by' => $account->hasFeature(FEATURE_REMOVE_CREATED_BY),
            'invoice_settings' => $account->hasFeature(FEATURE_INVOICE_SETTINGS),
        ];
        $invoice->invoice_fonts = $account->getFontsData();

        if ($design = $account->getCustomDesign($invoice->invoice_design_id)) {
            $invoice->invoice_design->javascript = $design;
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

        // translate the country names
        if ($invoice->client->country) {
            $invoice->client->country->name = trans('texts.country_' . $invoice->client->country->name);
        }
        if ($invoice->account->country) {
            $invoice->account->country->name = trans('texts.country_' . $invoice->account->country->name);
        }

        $data = [];
        $paymentTypes = $this->getPaymentTypes($account, $client, $invitation);
        $paymentURL = '';
        if (count($paymentTypes) == 1) {
            $paymentURL = $paymentTypes[0]['url'];
            if ($paymentTypes[0]['gatewayTypeId'] == GATEWAY_TYPE_CUSTOM) {
                // do nothing
            } elseif (! $account->isGatewayConfigured(GATEWAY_PAYPAL_EXPRESS)) {
                $paymentURL = URL::to($paymentURL);
            }
        }

        if ($wepayGateway = $account->getGatewayConfig(GATEWAY_WEPAY)) {
            $data['enableWePayACH'] = $wepayGateway->getAchEnabled();
        }

        $showApprove = $invoice->quote_invoice_id ? false : true;
        if ($invoice->due_date) {
            $showApprove = time() < strtotime($invoice->due_date);
        }
        if ($invoice->invoice_status_id >= INVOICE_STATUS_APPROVED) {
            $showApprove = false;
        }

        $data += [
            'account' => $account,
            'showApprove' => $showApprove,
            'showBreadcrumbs' => false,
            'invoice' => $invoice->hidePrivateFields(),
            'invitation' => $invitation,
            'invoiceLabels' => $account->getInvoiceLabels(),
            'contact' => $contact,
            'paymentTypes' => $paymentTypes,
            'paymentURL' => $paymentURL,
            'phantomjs' => Input::has('phantomjs'),
            'gatewayTypeId' => count($paymentTypes) == 1 ? $paymentTypes[0]['gatewayTypeId'] : false,
        ];

        if ($invoice->canBePaid()) {
            if ($paymentDriver = $account->paymentDriver($invitation, GATEWAY_TYPE_CREDIT_CARD)) {
                $data += [
                    'transactionToken' => $paymentDriver->createTransactionToken(),
                    'partialView' => $paymentDriver->partialView(),
                    'accountGateway' => $paymentDriver->accountGateway,
                ];
            }

            if ($accountGateway = $account->getGatewayByType(GATEWAY_TYPE_CUSTOM)) {
                $data += [
                    'customGatewayName' => $accountGateway->getConfigField('name'),
                    'customGatewayText' => $accountGateway->getConfigField('text'),
                ];
            }
        }

        if ($account->hasFeature(FEATURE_DOCUMENTS) && $this->canCreateZip()) {
            $zipDocs = $this->getInvoiceZipDocuments($invoice, $size);

            if (count($zipDocs) > 1) {
                $data['documentsZipURL'] = URL::to("client/documents/{$invitation->invitation_key}");
                $data['documentsZipSize'] = $size;
            }
        }

        return View::make('invoices.view', $data);
    }

    private function getPaymentTypes($account, $client, $invitation)
    {
        $links = [];

        foreach ($account->account_gateways as $accountGateway) {
            $paymentDriver = $accountGateway->paymentDriver($invitation);
            $links = array_merge($links, $paymentDriver->tokenLinks());
            $links = array_merge($links, $paymentDriver->paymentLinks());
        }

        return $links;
    }

    public function download($invitationKey)
    {
        if (! $invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return response()->view('error', [
                'error' => trans('texts.invoice_not_found'),
                'hideHeader' => true,
            ]);
        }

        $invoice = $invitation->invoice;
        $decode = ! request()->base64;
        $pdfString = $invoice->getPDFString($decode);

        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdfString));
        header('Content-disposition: attachment; filename="' . $invoice->getFileName() . '"');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        return $pdfString;
    }

    public function sign($invitationKey)
    {
        if (! $invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return RESULT_FAILURE;
        }

        if ($signature = Input::get('signature')) {
            $invitation->signature_base64 = $signature;
            $invitation->signature_date = date_create();
            $invitation->save();
        }

        session(['authorized:' . $invitation->invitation_key => true]);

        return RESULT_SUCCESS;
    }

    public function dashboard($contactKey = false)
    {
        if ($contactKey) {
            if (! $contact = Contact::where('contact_key', '=', $contactKey)->first()) {
                return $this->returnError();
            }
            Session::put('contact_key', $contactKey); // track current contact
        } elseif (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $client = $contact->client;
        $account = $client->account;

        if (request()->silent) {
            session(['silent:' . $client->id => true]);
            return redirect(request()->url());
        }

        $account->loadLocalizationSettings($client);
        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        $customer = false;

        if (! $account->enable_client_portal) {
            return $this->returnError();
        } elseif (! $account->enable_client_portal_dashboard) {
            return redirect()->to('/client/invoices/');
        }

        if ($paymentDriver = $account->paymentDriver(false, GATEWAY_TYPE_TOKEN)) {
            $customer = $paymentDriver->customer($client->id);
        }

        $data = [
            'color' => $color,
            'contact' => $contact,
            'account' => $account,
            'client' => $client,
            'gateway' => $account->getTokenGateway(),
            'paymentMethods' => $customer ? $customer->payment_methods : false,
            'transactionToken' => $paymentDriver ? $paymentDriver->createTransactionToken() : false,
        ];

        return response()->view('invited.dashboard', $data);
    }

    public function activityDatatable()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $client = $contact->client;

        $query = $this->activityRepo->findByClientId($client->id);
        $query->where('activities.adjustment', '!=', 0);

        return Datatable::query($query)
            ->addColumn('activities.id', function ($model) {
                return Utils::timestampToDateTimeString(strtotime($model->created_at));
            })
            ->addColumn('activity_type_id', function ($model) {
                $data = [
                    'client' => Utils::getClientDisplayName($model),
                    'user' => $model->is_system ? ('<i>' . trans('texts.system') . '</i>') : ($model->account_name),
                    'invoice' => $model->invoice,
                    'contact' => Utils::getClientDisplayName($model),
                    'payment' => $model->payment ? ' ' . $model->payment : '',
                    'credit' => $model->payment_amount ? Utils::formatMoney($model->credit, $model->currency_id, $model->country_id) : '',
                    'payment_amount' => $model->payment_amount ? Utils::formatMoney($model->payment_amount, $model->currency_id, $model->country_id) : null,
                    'adjustment' => $model->adjustment ? Utils::formatMoney($model->adjustment, $model->currency_id, $model->country_id) : null,
                ];

                return trans("texts.activity_{$model->activity_type_id}", $data);
            })
            ->addColumn('balance', function ($model) {
                return Utils::formatMoney($model->balance, $model->currency_id, $model->country_id);
            })
            ->addColumn('adjustment', function ($model) {
                return $model->adjustment != 0 ? Utils::wrapAdjustment($model->adjustment, $model->currency_id, $model->country_id) : '';
            })
            ->make();
    }

    public function recurringInvoiceIndex()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $account = $contact->account;
        $account->loadLocalizationSettings($contact->client);

        if (! $account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';
        $columns = ['frequency', 'start_date', 'end_date', 'invoice_total'];
        $client = $contact->client;

        if ($client->hasAutoBillConfigurableInvoices()) {
            $columns[] = 'auto_bill';
        }

        $data = [
            'color' => $color,
            'account' => $account,
            'client' => $client,
            'title' => trans('texts.recurring_invoices'),
            'entityType' => ENTITY_RECURRING_INVOICE,
            'columns' => Utils::trans($columns),
        ];

        return response()->view('public_list', $data);
    }

    public function invoiceIndex()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $account = $contact->account;
        $account->loadLocalizationSettings($contact->client);

        if (! $account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
            'color' => $color,
            'account' => $account,
            'client' => $contact->client,
            'title' => trans('texts.invoices'),
            'entityType' => ENTITY_INVOICE,
            'columns' => Utils::trans(['invoice_number', 'invoice_date', 'invoice_total', 'balance_due', 'due_date']),
        ];

        return response()->view('public_list', $data);
    }

    public function invoiceDatatable()
    {
        if (! $contact = $this->getContact()) {
            return '';
        }

        return $this->invoiceRepo->getClientDatatable($contact->id, ENTITY_INVOICE, Input::get('sSearch'));
    }

    public function recurringInvoiceDatatable()
    {
        if (! $contact = $this->getContact()) {
            return '';
        }

        return $this->invoiceRepo->getClientRecurringDatatable($contact->id);
    }

    public function paymentIndex()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $account = $contact->account;
        $account->loadLocalizationSettings($contact->client);

        if (! $account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
            'color' => $color,
            'account' => $account,
            'entityType' => ENTITY_PAYMENT,
            'title' => trans('texts.payments'),
            'columns' => Utils::trans(['invoice', 'transaction_reference', 'method', 'payment_amount', 'payment_date', 'status']),
        ];

        return response()->view('public_list', $data);
    }

    public function paymentDatatable()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }
        $payments = $this->paymentRepo->findForContact($contact->id, Input::get('sSearch'));

        return Datatable::query($payments)
                ->addColumn('invoice_number', function ($model) {
                    return $model->invitation_key ? link_to('/view/'.$model->invitation_key, $model->invoice_number)->toHtml() : $model->invoice_number;
                })
                ->addColumn('transaction_reference', function ($model) {
                    return $model->transaction_reference ? $model->transaction_reference : '<i>'.trans('texts.manual_entry').'</i>';
                })
                ->addColumn('payment_type', function ($model) {
                    return ($model->payment_type && ! $model->last4) ? $model->payment_type : ($model->account_gateway_id ? '<i>Online payment</i>' : '');
                })
                ->addColumn('amount', function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                })
                ->addColumn('payment_date', function ($model) {
                    return Utils::dateToString($model->payment_date);
                })
                ->addColumn('status', function ($model) {
                    return $this->getPaymentStatusLabel($model);
                })
                ->orderColumns('invoice_number', 'transaction_reference', 'payment_type', 'amount', 'payment_date')
                ->make();
    }

    private function getPaymentStatusLabel($model)
    {
        $label = trans('texts.status_' . strtolower($model->payment_status_name));
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
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $account = $contact->account;
        $account->loadLocalizationSettings($contact->client);

        if (! $account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
          'color' => $color,
          'account' => $account,
          'title' => trans('texts.quotes'),
          'entityType' => ENTITY_QUOTE,
          'columns' => Utils::trans(['quote_number', 'quote_date', 'quote_total', 'due_date']),
        ];

        return response()->view('public_list', $data);
    }

    public function quoteDatatable()
    {
        if (! $contact = $this->getContact()) {
            return false;
        }

        return $this->invoiceRepo->getClientDatatable($contact->id, ENTITY_QUOTE, Input::get('sSearch'));
    }

    public function creditIndex()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $account = $contact->account;
        $account->loadLocalizationSettings($contact->client);

        if (! $account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
          'color' => $color,
          'account' => $account,
          'title' => trans('texts.credits'),
          'entityType' => ENTITY_CREDIT,
          'columns' => Utils::trans(['credit_date', 'credit_amount', 'credit_balance', 'notes']),
        ];

        return response()->view('public_list', $data);
    }

    public function creditDatatable()
    {
        if (! $contact = $this->getContact()) {
            return false;
        }

        return $this->creditRepo->getClientDatatable($contact->client_id);
    }

    public function documentIndex()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $account = $contact->account;
        $account->loadLocalizationSettings($contact->client);

        if (! $account->enable_client_portal) {
            return $this->returnError();
        }

        $color = $account->primary_color ? $account->primary_color : '#0b4d78';

        $data = [
          'color' => $color,
          'account' => $account,
          'title' => trans('texts.documents'),
          'entityType' => ENTITY_DOCUMENT,
          'columns' => Utils::trans(['invoice_number', 'name', 'document_date', 'document_size']),
        ];

        return response()->view('public_list', $data);
    }

    public function documentDatatable()
    {
        if (! $contact = $this->getContact()) {
            return false;
        }

        return $this->documentRepo->getClientDatatable($contact->id, ENTITY_DOCUMENT, Input::get('sSearch'));
    }

    private function returnError($error = false)
    {
        return response()->view('error', [
            'error' => $error ?: trans('texts.invoice_not_found'),
            'hideHeader' => true,
            'account' => $this->getContact()->account,
        ]);
    }

    private function getContact()
    {
        $contactKey = session('contact_key');

        if (! $contactKey) {
            return false;
        }

        $contact = Contact::where('contact_key', '=', $contactKey)->first();

        if (! $contact || $contact->is_deleted) {
            return false;
        }

        return $contact;
    }

    public function getDocumentVFSJS($publicId, $name)
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $document = Document::scope($publicId, $contact->account_id)->first();

        if (! $document->isPDFEmbeddable()) {
            return Response::view('error', ['error' => 'Image does not exist!'], 404);
        }

        $authorized = false;
        if ($document->expense && $document->expense->client_id == $contact->client_id) {
            $authorized = true;
        } elseif ($document->invoice && $document->invoice->client_id == $contact->client_id) {
            $authorized = true;
        }

        if (! $authorized) {
            return Response::view('error', ['error' => 'Not authorized'], 403);
        }

        if (substr($name, -3) == '.js') {
            $name = substr($name, 0, -3);
        }

        $content = $document->preview ? $document->getRawPreview() : $document->getRaw();
        $content = 'ninjaAddVFSDoc('.json_encode(intval($publicId).'/'.strval($name)).',"'.base64_encode($content).'")';
        $response = Response::make($content, 200);
        $response->header('content-type', 'text/javascript');
        $response->header('cache-control', 'max-age=31536000');

        return $response;
    }

    protected function canCreateZip()
    {
        return function_exists('gmp_init');
    }

    protected function getInvoiceZipDocuments($invoice, &$size = 0)
    {
        $documents = $invoice->documents;

        foreach ($invoice->expenses as $expense) {
            if ($expense->invoice_documents) {
                $documents = $documents->merge($expense->documents);
            }
        }

        $documents = $documents->sortBy('size');

        $size = 0;
        $maxSize = MAX_ZIP_DOCUMENTS_SIZE * 1000;
        $toZip = [];
        foreach ($documents as $document) {
            if ($size + $document->size > $maxSize) {
                break;
            }

            if (! empty($toZip[$document->name])) {
                // This name is taken
                if ($toZip[$document->name]->hash != $document->hash) {
                    // 2 different files with the same name
                    $nameInfo = pathinfo($document->name);

                    for ($i = 1; ; $i++) {
                        $name = $nameInfo['filename'].' ('.$i.').'.$nameInfo['extension'];

                        if (empty($toZip[$name])) {
                            $toZip[$name] = $document;
                            $size += $document->size;
                            break;
                        } elseif ($toZip[$name]->hash == $document->hash) {
                            // We're not adding this after all
                            break;
                        }
                    }
                }
            } else {
                $toZip[$document->name] = $document;
                $size += $document->size;
            }
        }

        return $toZip;
    }

    public function getInvoiceDocumentsZip($invitationKey)
    {
        if (! $invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return $this->returnError();
        }

        Session::put('contact_key', $invitation->contact->contact_key); // track current contact

        $invoice = $invitation->invoice;

        $toZip = $this->getInvoiceZipDocuments($invoice);

        if (! count($toZip)) {
            return Response::view('error', ['error' => 'No documents small enough'], 404);
        }

        $zip = new ZipArchive($invitation->account->name.' Invoice '.$invoice->invoice_number.'.zip');

        return Response::stream(function () use ($toZip, $zip) {
            foreach ($toZip as $name => $document) {
                $fileStream = $document->getStream();
                if ($fileStream) {
                    $zip->init_file_stream_transfer($name, $document->size, ['time' => $document->created_at->timestamp]);
                    while ($buffer = fread($fileStream, 256000)) {
                        $zip->stream_file_part($buffer);
                    }
                    fclose($fileStream);
                    $zip->complete_file_stream();
                } else {
                    $zip->add_file($name, $document->getRaw());
                }
            }
            $zip->finish();
        }, 200);
    }

    public function getDocument($invitationKey, $publicId)
    {
        if (! $invitation = $this->invoiceRepo->findInvoiceByInvitation($invitationKey)) {
            return $this->returnError();
        }

        Session::put('contact_key', $invitation->contact->contact_key); // track current contact

        $clientId = $invitation->invoice->client_id;
        $document = Document::scope($publicId, $invitation->account_id)->firstOrFail();

        $authorized = false;
        if ($document->expense && $document->expense->invoice_documents && $document->expense->client_id == $invitation->invoice->client_id) {
            $authorized = true;
        } elseif ($document->invoice && $document->invoice->client_id == $invitation->invoice->client_id) {
            $authorized = true;
        }

        if (! $authorized) {
            return Response::view('error', ['error' => 'Not authorized'], 403);
        }

        return DocumentController::getDownloadResponse($document);
    }

    public function paymentMethods()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $client = $contact->client;
        $account = $client->account;

        $paymentDriver = $account->paymentDriver(false, GATEWAY_TYPE_TOKEN);
        $customer = $paymentDriver->customer($client->id);

        $data = [
            'account' => $account,
            'contact' => $contact,
            'color' => $account->primary_color ? $account->primary_color : '#0b4d78',
            'client' => $client,
            'paymentMethods' => $customer ? $customer->payment_methods : false,
            'gateway' => $account->getTokenGateway(),
            'title' => trans('texts.payment_methods'),
            'transactionToken' => $paymentDriver->createTransactionToken(),
        ];

        return response()->view('payments.paymentmethods', $data);
    }

    public function verifyPaymentMethod()
    {
        $publicId = Input::get('source_id');
        $amount1 = Input::get('verification1');
        $amount2 = Input::get('verification2');

        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $client = $contact->client;
        $account = $client->account;

        $paymentDriver = $account->paymentDriver(null, GATEWAY_TYPE_BANK_TRANSFER);
        $result = $paymentDriver->verifyBankAccount($client, $publicId, $amount1, $amount2);

        if (is_string($result)) {
            Session::flash('error', $result);
        } else {
            Session::flash('message', trans('texts.payment_method_verified'));
        }

        return redirect()->to($account->enable_client_portal_dashboard ? '/client/dashboard' : '/client/payment_methods/');
    }

    public function removePaymentMethod($publicId)
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $client = $contact->client;
        $account = $contact->account;

        $paymentDriver = $account->paymentDriver(false, GATEWAY_TYPE_TOKEN);
        $paymentMethod = PaymentMethod::clientId($client->id)
            ->wherePublicId($publicId)
            ->firstOrFail();

        try {
            $paymentDriver->removePaymentMethod($paymentMethod);
            Session::flash('message', trans('texts.payment_method_removed'));
        } catch (Exception $exception) {
            Session::flash('error', $exception->getMessage());
        }

        return redirect()->to($client->account->enable_client_portal_dashboard ? '/client/dashboard' : '/client/payment_methods/');
    }

    public function setDefaultPaymentMethod()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $client = $contact->client;
        $account = $client->account;

        $validator = Validator::make(Input::all(), ['source' => 'required']);
        if ($validator->fails()) {
            return Redirect::to($client->account->enable_client_portal_dashboard ? '/client/dashboard' : '/client/payment_methods/');
        }

        $paymentDriver = $account->paymentDriver(false, GATEWAY_TYPE_TOKEN);
        $paymentMethod = PaymentMethod::clientId($client->id)
            ->wherePublicId(Input::get('source'))
            ->firstOrFail();

        $customer = $paymentDriver->customer($client->id);
        $customer->default_payment_method_id = $paymentMethod->id;
        $customer->save();

        Session::flash('message', trans('texts.payment_method_set_as_default'));

        return redirect()->to($client->account->enable_client_portal_dashboard ? '/client/dashboard' : '/client/payment_methods/');
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

    public function setAutoBill()
    {
        if (! $contact = $this->getContact()) {
            return $this->returnError();
        }

        $client = $contact->client;

        $validator = Validator::make(Input::all(), ['public_id' => 'required']);

        if ($validator->fails()) {
            return Redirect::to('client/invoices/recurring');
        }

        $publicId = Input::get('public_id');
        $enable = Input::get('enable');
        $invoice = $client->invoices()->where('public_id', intval($publicId))->first();

        if ($invoice && $invoice->is_recurring && ($invoice->auto_bill == AUTO_BILL_OPT_IN || $invoice->auto_bill == AUTO_BILL_OPT_OUT)) {
            $invoice->client_enable_auto_bill = $enable ? true : false;
            $invoice->save();
        }

        return Redirect::to('client/invoices/recurring');
    }
}
