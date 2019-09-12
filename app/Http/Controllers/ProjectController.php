<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateProjectChartData;
use App\Http\Requests\CreateProjectRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Client;
use App\Models\Project;
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
        $userId = Auth::user()->filterIdByEntity(ENTITY_PROJECT);

        return $this->projectService->getDatatable($search, $userId);
    }

    public function show(ProjectRequest $request)
    {
        $account = auth()->user()->account;
        $project = $request->entity();
        $chartData = dispatch_now(new GenerateProjectChartData($project));

        $data = [
            'account' => auth()->user()->account,
            'project' => $project,
            'title' => trans('texts.view_project'),
            'showBreadcrumbs' => false,
            'chartData' => $chartData,
        ];

        return View::make('projects.show', $data);
    }

    public function create(ProjectRequest $request)
    {
        $data = [
            'account' => auth()->user()->account,
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
            'account' => auth()->user()->account,
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

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore', 'invoice'])) {
            return self::bulk();
        }

        return redirect()->to($project->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        if ($action == 'invoice') {
            $data = [];
            $clientPublicId = false;
            $lastClientId = false;
            $lastProjectId = false;
            $projects = Project::scope($ids)
                ->with(['client', 'tasks' => function ($query) {
                    $query->whereNull('invoice_id');
                }])
                ->get();
            foreach ($projects as $project) {
                if (! $clientPublicId) {
                    $clientPublicId = $project->client->public_id;
                }
                if ($lastClientId && $lastClientId != $project->client_id) {
                    return redirect('projects')->withError(trans('texts.project_error_multiple_clients'));
                }
                $lastClientId = $project->client_id;

                foreach ($project->tasks as $task) {
                    if ($task->is_running) {
                        return redirect('projects')->withError(trans('texts.task_error_running'));
                    }
                    $showProject = $lastProjectId != $task->project_id;
                    $data[] = [
                        'publicId' => $task->public_id,
                        'description' => $task->present()->invoiceDescription(auth()->user()->account, $showProject),
                        'duration' => $task->getHours(),
                        'cost' => $task->getRate(),
                    ];
                    $lastProjectId = $task->project_id;
                }
            }
            return redirect("invoices/create/{$clientPublicId}")->with('tasks', $data);
        } else {
            $count = $this->projectService->bulk($ids, $action);

            if ($count > 0) {
                $field = $count == 1 ? "{$action}d_project" : "{$action}d_projects";
                $message = trans("texts.$field", ['count' => $count]);
                Session::flash('message', $message);
            }

            return redirect()->to('/projects');
        }
    }
}
