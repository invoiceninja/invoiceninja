<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProjectRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Ninja\Datatables\ProjectDatatable;
use App\Ninja\Repositories\ProjectRepository;
use App\Services\ProjectService;
use Auth;
use Input;
use Session;
use View;

class ProjectController extends BaseController
{
    protected $projectRepo;
    protected $projectService;
    protected $entityType = ENTITY_PROJECT;

    public function __construct(ProjectRepository $projectRepo, ProjectService $projectService)
    {
        $this->projectRepo = $projectRepo;
        $this->projectService = $projectService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_PROJECT,
            'datatable' => new ProjectDatatable(),
            'title' => trans('texts.projects'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        $search = Input::get('sSearch');
        $userId = Auth::user()->filterId();

        return $this->projectService->getDatatable($search, $userId);
    }

    public function create(ProjectRequest $request)
    {
        $data = [
            'project' => null,
            'method' => 'POST',
            'url' => 'projects',
            'title' => trans('texts.new_project'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $request->client_id,
        ];

        return View::make('projects.edit', $data);
    }

    public function edit(ProjectRequest $request)
    {
        $project = $request->entity();

        $data = [
            'project' => $project,
            'method' => 'PUT',
            'url' => 'projects/' . $project->public_id,
            'title' => trans('texts.edit_project'),
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $project->client ? $project->client->public_id : null,
        ];

        return View::make('projects.edit', $data);
    }

    public function store(CreateProjectRequest $request)
    {
        $project = $this->projectService->save($request->input());

        Session::flash('message', trans('texts.created_project'));

        return redirect()->to($project->getRoute());
    }

    public function update(UpdateProjectRequest $request)
    {
        $project = $this->projectService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_project'));

        return redirect()->to($project->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->projectService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_project" : "{$action}d_projects";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return redirect()->to('/projects');
    }
}
