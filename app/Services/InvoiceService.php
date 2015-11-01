<?php namespace App\Services;

use App\Services\BaseService;
use App\Ninja\Repositories\InvoiceRepository;
use App\Ninja\Repositories\ClientRepository;
use App\Events\QuoteInvitationWasApproved;
use App\Models\Invitation;

class InvoiceService extends BaseService
{
    protected $clientRepo;
    protected $invoiceRepo;

    public function __construct(ClientRepository $clientRepo, InvoiceRepository $invoiceRepo)
    {
        $this->clientRepo = $clientRepo;
        $this->invoiceRepo = $invoiceRepo;
    }

    protected function getRepo()
    {
        return $this->invoiceRepo;
    }

    public function save($data)
    {
        if (isset($data['client'])) {
            $client = $this->clientRepo->save($data['client']);
            $data['client_id'] = $client->id;
        }

        $invoice = $this->invoiceRepo->save($data);
        
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

    public function approveQuote($quote, $invitation = null)
    {
        if (!$quote->is_quote || $quote->quote_invoice_id) {
            return null;
        }
        
        $invoice = $this->invoiceRepo->cloneInvoice($quote, $quote->id);

        if (!$invitation) {
            return $invoice;
        }

        event(new QuoteInvitationWasApproved($invoice, $invitation));

        foreach ($invoice->invitations as $invoiceInvitation) {
            if ($invitation->contact_id == $invoiceInvitation->contact_id) {
                return $invoiceInvitation->invitation_key;
            }
        }
    } 
}