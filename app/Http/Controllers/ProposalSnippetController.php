<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProposalSnippetRequest;
use App\Http\Requests\ProposalSnippetRequest;
use App\Http\Requests\UpdateProposalSnippetRequest;
use App\Models\Invoice;
use App\Models\ProposalSnippet;
use App\Models\ProposalCategory;
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
            'snippet' => null,
            'method' => 'POST',
            'url' => 'proposals/snippets',
            'title' => trans('texts.new_proposal_snippet'),
            'categories' => ProposalCategory::scope()->orderBy('name')->get(),
            'categoryPublicId' => 0,
        ];

        return View::make('proposals/snippets/edit', $data);
    }

    public function show($publicId)
    {
        Session::reflash();

        return redirect("proposals/snippets/$publicId/edit");
    }

    public function edit(ProposalSnippetRequest $request)
    {
        $proposalSnippet = $request->entity();

        $data = [
            'account' => auth()->user()->account,
            'snippet' => $proposalSnippet,
            'method' => 'PUT',
            'url' => 'proposals/snippets/' . $proposalSnippet->public_id,
            'title' => trans('texts.edit_proposal_snippet'),
            'categories' => ProposalCategory::scope()->orderBy('name')->get(),
            'categoryPublicId' => $proposalSnippet->proposal_category ? $proposalSnippet->proposal_category->public_id : null,
        ];

        return View::make('proposals/snippets.edit', $data);
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
