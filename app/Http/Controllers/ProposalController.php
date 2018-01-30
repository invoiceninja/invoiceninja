<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateProposalChartData;
use App\Http\Requests\CreateProposalRequest;
use App\Http\Requests\ProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\ProposalTemplate;
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
    protected $entityType = ENTITY_PROPOSAL;

    /*
    public function __construct(ProposalRepository $proposalRepo, ProposalService $proposalService)
    {
        $this->proposalRepo = $proposalRepo;
        $this->proposalService = $proposalService;
    }
    */

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
        $userId = Auth::user()->filterId();

        return $this->proposalService->getDatatable($search, $userId);
    }

    /*
    public function show(ProposalRequest $request)
    {
        $account = auth()->user()->account;
        $proposal = $request->entity();

        $data = [
            'account' => auth()->user()->account,
            'proposal' => $proposal,
            'title' => trans('texts.view_proposal'),
            'showBreadcrumbs' => false,
        ];

        return View::make('proposals.show', $data);
    }
    */

    public function create(ProposalRequest $request)
    {
        $data = [
            'account' => auth()->user()->account,
            'proposal' => null,
            'method' => 'POST',
            'url' => 'proposals',
            'title' => trans('texts.new_proposal'),
            'quotes' => Invoice::scope()->with('client.contacts')->quotes()->orderBy('id')->get(),
            'templates' => ProposalTemplate::scope()->orderBy('name')->get(),
            'quotePublicId' => $request->quote_id,
        ];

        return View::make('proposals.edit', $data);
    }

    public function edit(ProposalRequest $request)
    {
        $proposal = $request->entity();

        $data = [
            'account' => auth()->user()->account,
            'proposal' => $proposal,
            'method' => 'PUT',
            'url' => 'proposals/' . $proposal->public_id,
            'title' => trans('texts.edit_proposal'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $proposal->client ? $proposal->client->public_id : null,
        ];

        return View::make('proposals.edit', $data);
    }

    public function store(CreateProposalRequest $request)
    {
        $proposal = $this->proposalService->save($request->input());

        Session::flash('message', trans('texts.created_proposal'));

        return redirect()->to($proposal->getRoute());
    }

    public function update(UpdateProposalRequest $request)
    {
        $proposal = $this->proposalService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_proposal'));

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore'])) {
            return self::bulk();
        }

        return redirect()->to($proposal->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        $count = $this->proposalService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_proposal" : "{$action}d_proposals";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return redirect()->to('/proposals');
    }
}
