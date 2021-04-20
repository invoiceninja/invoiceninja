<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProposalRequest;
use App\Http\Requests\ProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Jobs\SendInvoiceEmail;
use App\Jobs\ConvertProposalToPdf;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\ProposalTemplate;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Datatables\ProposalDatatable;
use App\Ninja\Repositories\ProposalRepository;
use App\Services\ProposalService;
use Auth;
use Input;
use Session;
use View;

class ProposalController extends BaseController
{
    protected $proposalRepo;
    protected $proposalService;
    protected $contactMailer;

    protected $entityType = ENTITY_PROPOSAL;

    public function __construct(ProposalRepository $proposalRepo, ProposalService $proposalService, ContactMailer $contactMailer)
    {
        $this->proposalRepo = $proposalRepo;
        $this->proposalService = $proposalService;
        $this->contactMailer = $contactMailer;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PROPOSAL,
            'datatable' => new ProposalDatatable(),
            'title' => trans('texts.proposals'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        $search = Input::get('sSearch');
        //$userId = Auth::user()->filterId();
        $userId = Auth::user()->filterIdByEntity(ENTITY_PROPOSAL);

        return $this->proposalService->getDatatable($search, $userId);
    }

    public function create(ProposalRequest $request)
    {
        $data = array_merge($this->getViewmodel(), [
            'proposal' => null,
            'method' => 'POST',
            'url' => 'proposals',
            'title' => trans('texts.new_proposal'),
            'invoices' => Invoice::scope()->with('client.contacts', 'client.country')->unapprovedQuotes()->orderBy('id')->get(),
            'invoicePublicId' => $request->invoice_id,
            'templatePublicId' => $request->proposal_template_id,
        ]);

        return View::make('proposals.edit', $data);
    }

    public function show($publicId)
    {
        Session::reflash();

        return redirect("proposals/$publicId/edit");
    }

    public function edit(ProposalRequest $request)
    {
        $proposal = $request->entity();

        $data = array_merge($this->getViewmodel($proposal), [
            'proposal' => $proposal,
            'entity' => $proposal,
            'method' => 'PUT',
            'url' => 'proposals/' . $proposal->public_id,
            'title' => trans('texts.edit_proposal'),
            'invoices' => Invoice::scope()->with('client.contacts', 'client.country')->withActiveOrSelected($proposal->invoice_id)->unapprovedQuotes($proposal->invoice_id)->orderBy('id')->get(),
            'invoicePublicId' => $proposal->invoice ? $proposal->invoice->public_id : null,
            'templatePublicId' => $proposal->proposal_template ? $proposal->proposal_template->public_id : null,
        ]);

        return View::make('proposals.edit', $data);
    }

    private function getViewmodel($proposal = false)
    {
        $account = auth()->user()->account;
        $templates = ProposalTemplate::whereAccountId($account->id)->withActiveOrSelected($proposal ? $proposal->proposal_template_id : false)->orderBy('name')->get();

        if (! $templates->count()) {
            $templates = ProposalTemplate::whereNull('account_id')->orderBy('name')->get();
        }

        $data = [
            'templates' => $templates,
            'account' => $account,
        ];

        return $data;
    }

    public function store(CreateProposalRequest $request)
    {
        $proposal = $this->proposalService->save($request->input());
        $action = Input::get('action');

        if ($action == 'email') {
            $this->dispatch(new SendInvoiceEmail($proposal->invoice, auth()->user()->id, false, false, $proposal));
            Session::flash('message', trans('texts.emailed_proposal'));
        } else {
            Session::flash('message', trans('texts.created_proposal'));
        }

        return redirect()->to($proposal->getRoute());
    }

    public function update(UpdateProposalRequest $request)
    {
        $proposal = $this->proposalService->save($request->input(), $request->entity());
        $action = Input::get('action');

        if (in_array($action, ['archive', 'delete', 'restore'])) {
            return self::bulk();
        }

        if ($action == 'email') {
            $this->dispatch(new SendInvoiceEmail($proposal->invoice, auth()->user()->id, false, false, $proposal));
            Session::flash('message', trans('texts.emailed_proposal'));
        } else {
            Session::flash('message', trans('texts.updated_proposal'));
        }

        return redirect()->to($proposal->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('bulk_action') ?: Input::get('action');
        $ids = Input::get('bulk_public_id') ?: (Input::get('public_id') ?: Input::get('ids'));

        $count = $this->proposalService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_proposal" : "{$action}d_proposals";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return redirect()->to('/proposals');
    }

    public function download(ProposalRequest $request)
    {
        $proposal = $request->entity();

        $pdf = dispatch_now(new ConvertProposalToPdf($proposal));

        $this->downloadResponse($proposal->getFilename(), $pdf);
    }
}
