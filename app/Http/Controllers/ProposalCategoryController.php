<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProposalCategoryRequest;
use App\Http\Requests\ProposalCategoryRequest;
use App\Http\Requests\UpdateProposalCategoryRequest;
use App\Models\Invoice;
use App\Models\ProposalCategory;
use App\Ninja\Datatables\ProposalCategoryDatatable;
use App\Ninja\Repositories\ProposalCategoryRepository;
use App\Services\ProposalCategoryService;
use Auth;
use Input;
use Session;
use View;

class ProposalCategoryController extends BaseController
{
    protected $proposalCategoryRepo;
    protected $proposalCategoryService;
    protected $entityType = ENTITY_PROPOSAL_CATEGORY;

    public function __construct(ProposalCategoryRepository $proposalCategoryRepo, ProposalCategoryService $proposalCategoryService)
    {
        $this->proposalCategoryRepo = $proposalCategoryRepo;
        $this->proposalCategoryService = $proposalCategoryService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PROPOSAL_CATEGORY,
            'datatable' => new ProposalCategoryDatatable(),
            'title' => trans('texts.proposal_categories'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        $search = Input::get('sSearch');
        $userId = Auth::user()->filterId();

        return $this->proposalCategoryService->getDatatable($search, $userId);
    }

    public function create(ProposalCategoryRequest $request)
    {
        $data = [
            'account' => auth()->user()->account,
            'category' => null,
            'method' => 'POST',
            'url' => 'proposals/categories',
            'title' => trans('texts.new_proposal_category'),
            'quotes' => Invoice::scope()->with('client.contacts')->quotes()->orderBy('id')->get(),
            'templates' => ProposalCategory::scope()->orderBy('name')->get(),
            'quotePublicId' => $request->quote_id,
        ];

        return View::make('proposals/categories.edit', $data);
    }

    public function show($publicId)
    {
        Session::reflash();

        return redirect("proposals/categories/$publicId/edit");
    }

    public function edit(ProposalCategoryRequest $request)
    {
        $proposalCategory = $request->entity();

        $data = [
            'account' => auth()->user()->account,
            'category' => $proposalCategory,
            'method' => 'PUT',
            'url' => 'proposals/categories/' . $proposalCategory->public_id,
            'title' => trans('texts.edit_proposal_category'),
        ];

        return View::make('proposals/categories.edit', $data);
    }

    public function store(CreateProposalCategoryRequest $request)
    {
        $proposalCategory = $this->proposalCategoryService->save($request->input());

        Session::flash('message', trans('texts.created_proposal_category'));

        return redirect()->to($proposalCategory->getRoute());
    }

    public function update(UpdateProposalCategoryRequest $request)
    {
        $proposalCategory = $this->proposalCategoryService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_proposal_category'));

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore'])) {
            return self::bulk();
        }

        return redirect()->to($proposalCategory->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        $count = $this->proposalCategoryService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_proposal_category" : "{$action}d_proposal_categories";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return redirect()->to('/proposals/categories');
    }
}
