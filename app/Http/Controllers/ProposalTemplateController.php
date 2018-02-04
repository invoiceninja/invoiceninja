<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProposalTemplateRequest;
use App\Http\Requests\ProposalTemplateRequest;
use App\Http\Requests\UpdateProposalTemplateRequest;
use App\Models\Invoice;
use App\Models\ProposalTemplate;
use App\Models\ProposalSnippet;
use App\Ninja\Datatables\ProposalTemplateDatatable;
use App\Ninja\Repositories\ProposalTemplateRepository;
use App\Services\ProposalTemplateService;
use Auth;
use Input;
use Session;
use View;

class ProposalTemplateController extends BaseController
{
    protected $proposalTemplateRepo;
    protected $proposalTemplateService;
    protected $entityType = ENTITY_PROPOSAL_TEMPLATE;

    public function __construct(ProposalTemplateRepository $proposalTemplateRepo, ProposalTemplateService $proposalTemplateService)
    {
        $this->proposalTemplateRepo = $proposalTemplateRepo;
        $this->proposalTemplateService = $proposalTemplateService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PROPOSAL_TEMPLATE,
            'datatable' => new ProposalTemplateDatatable(),
            'title' => trans('texts.proposal_templates'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        $search = Input::get('sSearch');
        $userId = Auth::user()->filterId();

        return $this->proposalTemplateService->getDatatable($search, $userId);
    }

    public function create(ProposalTemplateRequest $request)
    {
        $data = [
            'account' => auth()->user()->account,
            'template' => null,
            'method' => 'POST',
            'url' => 'proposals/templates',
            'title' => trans('texts.new_proposal_template'),
            'templates' => ProposalTemplate::scope()->orderBy('name')->get(),
            'snippets' => ProposalSnippet::scope()->with('proposal_category')->orderBy('name')->get(),
        ];

        return View::make('proposals/templates/edit', $data);
    }

    public function show($publicId)
    {
        Session::reflash();

        return redirect("proposals/templates/$publicId/edit");
    }

    public function edit(ProposalTemplateRequest $request)
    {
        $proposalTemplate = $request->entity();

        $data = [
            'account' => auth()->user()->account,
            'template' => $proposalTemplate,
            'method' => 'PUT',
            'url' => 'proposals/templates/' . $proposalTemplate->public_id,
            'title' => trans('texts.edit_proposal_template'),
            'templates' => ProposalTemplate::scope()->orderBy('name')->get(),
            'snippets' => ProposalSnippet::scope()->with('proposal_category')->orderBy('name')->get(),
        ];

        return View::make('proposals/templates/edit', $data);
    }

    public function store(CreateProposalTemplateRequest $request)
    {
        $proposalTemplate = $this->proposalTemplateService->save($request->input());

        Session::flash('message', trans('texts.created_proposal_template'));

        return redirect()->to($proposalTemplate->getRoute());
    }

    public function update(UpdateProposalTemplateRequest $request)
    {
        $proposalTemplate = $this->proposalTemplateService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_proposal_template'));

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore'])) {
            return self::bulk();
        }

        return redirect()->to($proposalTemplate->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        $count = $this->proposalTemplateService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_proposal_template" : "{$action}d_proposal_templates";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return redirect()->to('/proposals/templates');
    }
}
