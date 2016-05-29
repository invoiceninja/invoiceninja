<?php namespace App\Services;

use Auth;
use Utils;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\ClientRepository;
use App\Events\QuoteInvitationWasApproved;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Payment;
use App\Ninja\Datatables\InvoiceDatatable;

class InvoiceService extends BaseService
{
    protected $clientRepo;
    protected $invoiceRepo;
    protected $datatableService;

    public function __construct(ClientRepository $clientRepo, InvoiceRepository $invoiceRepo, DatatableService $datatableService)
    {
        $this->clientRepo = $clientRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->invoiceRepo;
    }

    public function save($data, $invoice = null)
    {
        if (isset($data['client'])) {
            $canSaveClient = false;
            $clientPublicId = array_get($data, 'client.public_id') ?: array_get($data, 'client.id');
            if (empty($clientPublicId) || $clientPublicId == '-1') {
                $canSaveClient = Auth::user()->can('create', ENTITY_CLIENT);
            } else {
                $canSaveClient = Auth::user()->can('edit', Client::scope($clientPublicId)->first());
            }
            if ($canSaveClient) {
                $client = $this->clientRepo->save($data['client']);
                $data['client_id'] = $client->id;
            }
        }

        $invoice = $this->invoiceRepo->save($data, $invoice);

        $client = $invoice->client;
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

    public function convertQuote($quote, $invitation = null)
    {
        $invoice = $this->invoiceRepo->cloneInvoice($quote, $quote->id);
        if (!$invitation) {
            return $invoice;
        }

        foreach ($invoice->invitations as $invoiceInvitation) {
            if ($invitation->contact_id == $invoiceInvitation->contact_id) {
                return $invoiceInvitation->invitation_key;
            }
        }
    }

    public function approveQuote($quote, $invitation = null)
    {
        $account = $quote->account;

        if (!$quote->isType(INVOICE_TYPE_QUOTE) || $quote->quote_invoice_id) {
            return null;
        }

        if ($account->auto_convert_quote || ! $account->hasFeature(FEATURE_QUOTES)) {
            $invoice = $this->convertQuote($quote, $invitation);

            event(new QuoteInvitationWasApproved($quote, $invoice, $invitation));

            return $invoice;
        } else {
            $quote->markApproved();

            event(new QuoteInvitationWasApproved($quote, null, $invitation));

            foreach ($quote->invitations as $invoiceInvitation) {
                if ($invitation->contact_id == $invoiceInvitation->contact_id) {
                    return $invoiceInvitation->invitation_key;
                }
            }
        }
    }

    public function getDatatable($accountId, $clientPublicId = null, $entityType, $search)
    {
        $datatable = new InvoiceDatatable( ! $clientPublicId, $clientPublicId);
        $datatable->entityType = $entityType;

        $query = $this->invoiceRepo->getInvoices($accountId, $clientPublicId, $entityType, $search)
                    ->where('invoices.invoice_type_id', '=', $entityType == ENTITY_QUOTE ? INVOICE_TYPE_QUOTE : INVOICE_TYPE_STANDARD);

        if(!Utils::hasPermission('view_all')){
            $query->where('invoices.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }

}
