<?php

namespace App\Services;

use App\Events\QuoteInvitationWasApproved;
use App\Models\Client;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Ninja\Datatables\InvoiceDatatable;
use App\Ninja\Repositories\ClientRepository;
use App\Ninja\Repositories\InvoiceRepository;
use Auth;
use Utils;

class InvoiceService extends BaseService
{
    /**
     * @var ClientRepository
     */
    protected $clientRepo;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * InvoiceService constructor.
     *
     * @param ClientRepository  $clientRepo
     * @param InvoiceRepository $invoiceRepo
     * @param DatatableService  $datatableService
     */
    public function __construct(
        ClientRepository $clientRepo,
        InvoiceRepository $invoiceRepo,
        DatatableService $datatableService
    ) {
        $this->clientRepo = $clientRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return InvoiceRepository
     */
    protected function getRepo()
    {
        return $this->invoiceRepo;
    }

    /**
     * @param array        $data
     * @param Invoice|null $invoice
     *
     * @return \App\Models\Invoice|Invoice|mixed
     */
    public function save(array $data, Invoice $invoice = null)
    {
        if (isset($data['client'])) {
            $canSaveClient = false;
            $canViewClient = false;
            $clientPublicId = array_get($data, 'client.public_id') ?: array_get($data, 'client.id');
            if (empty($clientPublicId) || $clientPublicId == '-1') {
                $canSaveClient = Auth::user()->can('create', ENTITY_CLIENT);
            } else {
                $client = Client::scope($clientPublicId)->first();
                $canSaveClient = Auth::user()->can('edit', $client);
                $canViewClient = Auth::user()->can('view', $client);
            }
            if ($canSaveClient) {
                $client = $this->clientRepo->save($data['client']);
            }
            if ($canSaveClient || $canViewClient) {
                $data['client_id'] = $client->id;
            }
        }

        return $this->invoiceRepo->save($data, $invoice);
    }

    /**
     * @param $quote
     * @param Invitation|null $invitation
     *
     * @return mixed
     */
    public function convertQuote($quote)
    {
        return $this->invoiceRepo->cloneInvoice($quote, $quote->id);
    }

    /**
     * @param $quote
     * @param Invitation|null $invitation
     *
     * @return mixed|null
     */
    public function approveQuote($quote, Invitation $invitation = null)
    {
        $account = $quote->account;

        if (! $account->hasFeature(FEATURE_QUOTES) || ! $quote->isType(INVOICE_TYPE_QUOTE) || $quote->quote_invoice_id) {
            return null;
        }

        event(new QuoteInvitationWasApproved($quote, $invitation));

        if ($account->auto_convert_quote) {
            $invoice = $this->convertQuote($quote);

            foreach ($invoice->invitations as $invoiceInvitation) {
                if ($invitation->contact_id == $invoiceInvitation->contact_id) {
                    $invitation = $invoiceInvitation;
                }
            }
        } else {
            $quote->markApproved();
        }

        return $invitation->invitation_key;
    }

    public function getDatatable($accountId, $clientPublicId, $entityType, $search)
    {
        $datatable = new InvoiceDatatable(true, $clientPublicId);
        $datatable->entityType = $entityType;

        $query = $this->invoiceRepo->getInvoices($accountId, $clientPublicId, $entityType, $search)
                    ->where('invoices.invoice_type_id', '=', $entityType == ENTITY_QUOTE ? INVOICE_TYPE_QUOTE : INVOICE_TYPE_STANDARD);

        if (! Utils::hasPermission('view_all')) {
            $query->where('invoices.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
