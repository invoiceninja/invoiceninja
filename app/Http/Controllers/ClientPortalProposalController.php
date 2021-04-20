<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Document;
use App\Models\Invitation;
use App\Ninja\Repositories\ProposalRepository;
use App\Jobs\ConvertProposalToPdf;

class ClientPortalProposalController extends BaseController
{
    private $invoiceRepo;
    private $paymentRepo;
    private $documentRepo;
    private $propoosalRepo;

    public function __construct(ProposalRepository $propoosalRepo)
    {
        $this->propoosalRepo = $propoosalRepo;
    }

    public function viewProposal($invitationKey)
    {
        if (! $invitation = $this->propoosalRepo->findInvitationByKey($invitationKey)) {
            return $this->returnError(trans('texts.proposal_not_found'));
        }

        $account = $invitation->account;
        $proposal = $invitation->proposal;
        $invoiceInvitation = Invitation::whereContactId($invitation->contact_id)
                ->whereInvoiceId($proposal->invoice_id)
                ->firstOrFail();

        $data = [
            'proposal' => $proposal,
            'account' => $account,
            'invoiceInvitation' => $invoiceInvitation,
            'proposalInvitation' => $invitation,
        ];

        if (request()->phantomjs) {
            return $proposal->present()->htmlDocument;
        } else {
            return view('invited.proposal', $data);
        }
    }

    public function downloadProposal($invitationKey)
    {
        if (! $invitation = $this->propoosalRepo->findInvitationByKey($invitationKey)) {
            return $this->returnError(trans('texts.proposal_not_found'));
        }

        $proposal = $invitation->proposal;

        $pdf = dispatch_now(new ConvertProposalToPdf($proposal));

        $this->downloadResponse($proposal->getFilename(), $pdf);
    }

    public function getProposalImage($accountKey, $documentKey)
    {
        $account = Account::whereAccountKey($accountKey)
                        ->firstOrFail();

        $document = Document::whereAccountId($account->id)
                        ->whereDocumentKey($documentKey)
                        ->whereIsProposal(true)
                        ->firstOrFail();

        return DocumentController::getDownloadResponse($document);
    }
}
