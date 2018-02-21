<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProposalTemplateRequest;
use App\Http\Requests\ProposalTemplateRequest;
use App\Http\Requests\UpdateProposalTemplateRequest;
use App\Models\Invoice;
use App\Models\ProposalTemplate;
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
        $data = array_merge($this->getViewmodel(), [
            'template' => null,
            'method' => 'POST',
            'url' => 'proposals/templates',
            'title' => trans('texts.new_proposal_template'),
        ]);

        return View::make('proposals/templates/edit', $data);
    }

    private function getViewmodel()
    {
        $customTemplates = ProposalTemplate::scope()->orderBy('name')->get();
        $defaultTemplates = ProposalTemplate::whereNull('account_id')->orderBy('public_id')->get();

        $options = [];
        $customLabel = trans('texts.custom');
        $defaultLabel = trans('texts.default');

        foreach ($customTemplates as $template) {
            if (! isset($options[$customLabel])) {
                $options[$customLabel] = [];
            }
            $options[trans('texts.custom')][$template->public_id] = $template->name;
        }
        foreach ($defaultTemplates as $template) {
            if (! isset($options[$defaultLabel])) {
                $options[$defaultLabel] = [];
            }
            $options[trans('texts.default')][$template->public_id] = $template->name;
        }

        $data = [
            'account' => auth()->user()->account,
            'customTemplates' => $customTemplates,
            'defaultTemplates' => $defaultTemplates,
            'templateOptions' => $options,
        ];

        return $data;
    }

    public function show($publicId)
    {
        Session::reflash();

        return redirect("proposals/templates/$publicId/edit");
    }

    public function edit(ProposalTemplateRequest $request, $publicId = false, $clone = false)
    {
        $template = $request->entity();

        if ($clone) {
            $template->id = null;
            $template->public_id = null;
            $template->name = '';
            $template->private_notes = '';
            $method = 'POST';
            $url = 'proposals/templates';
        } else {
            $method = 'PUT';
            $url = 'proposals/templates/' . $template->public_id;
        }

        $data = array_merge($this->getViewmodel(), [
            'template' => $template,
            'entity' => $clone ? false : $template,
            'method' => $method,
            'url' => $url,
            'title' => trans('texts.edit_proposal_template'),
        ]);

        return View::make('proposals/templates/edit', $data);
    }

    public function cloneProposal(ProposalTemplateRequest $request, $publicId)
    {
        return self::edit($request, $publicId, true);
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
