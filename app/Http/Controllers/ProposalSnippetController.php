<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProposalSnippetRequest;
use App\Http\Requests\ProposalSnippetRequest;
use App\Http\Requests\UpdateProposalSnippetRequest;
use App\Models\Invoice;
use App\Models\ProposalSnippet;
use App\Ninja\Datatables\ProposalSnippetDatatable;
use App\Ninja\Repositories\ProposalSnippetRepository;
use App\Services\ProposalSnippetService;
use Auth;
use Input;
use Session;
use View;

class ProposalSnippetController extends BaseController
{
    protected $proposalSnippetRepo;
    protected $proposalSnippetService;
    protected $entityType = ENTITY_PROPOSAL_SNIPPET;

    public function __construct(ProposalSnippetRepository $proposalSnippetRepo, ProposalSnippetService $proposalSnippetService)
    {
        $this->proposalSnippetRepo = $proposalSnippetRepo;
        $this->proposalSnippetService = $proposalSnippetService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PROPOSAL_SNIPPET,
            'datatable' => new ProposalSnippetDatatable(),
            'title' => trans('texts.proposal_snippets'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        $search = Input::get('sSearch');
        $userId = Auth::user()->filterId();

        return $this->proposalSnippetService->getDatatable($search, $userId);
    }

    public function create(ProposalSnippetRequest $request)
    {
        $data = [
            'account' => auth()->user()->account,
            'proposalSnippet' => null,
            'method' => 'POST',
            'url' => 'proposal_snippets',
            'title' => trans('texts.new_proposal_snippet'),
            'quotes' => Invoice::scope()->with('client.contacts')->quotes()->orderBy('id')->get(),
            'templates' => ProposalSnippet::scope()->orderBy('name')->get(),
            'quotePublicId' => $request->quote_id,
        ];

        return View::make('proposals/snippets/edit', $data);
    }

    public function edit(ProposalSnippetRequest $request)
    {
        $proposalSnippet = $request->entity();

        $data = [
            'account' => auth()->user()->account,
            'proposalSnippet' => $proposalSnippet,
            'method' => 'PUT',
            'url' => 'proposal_snippets/' . $proposalSnippet->public_id,
            'title' => trans('texts.edit_proposal_snippet'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $proposalSnippet->client ? $proposalSnippet->client->public_id : null,
        ];

        return View::make('proposals/snippets/edit', $data);
    }

    public function store(CreateProposalSnippetRequest $request)
    {
        $proposalSnippet = $this->proposalSnippetService->save($request->input());

        Session::flash('message', trans('texts.created_proposal_snippet'));

        return redirect()->to($proposalSnippet->getRoute());
    }

    public function update(UpdateProposalSnippetRequest $request)
    {
        $proposalSnippet = $this->proposalSnippetService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_proposal_snippet'));

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore'])) {
            return self::bulk();
        }

        return redirect()->to($proposalSnippet->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        $count = $this->proposalSnippetService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_proposal_snippet" : "{$action}d_proposal_snippets";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return redirect()->to('/proposal_snippets');
    }
}
