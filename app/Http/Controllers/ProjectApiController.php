<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProjectRequest;
use App\Http\Requests\ProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Ninja\Repositories\ProjectRepository;
use App\Services\ProjectService;
use Auth;
use Input;
use Session;
use View;

/**
 * Class ProjectApiController
 * @package App\Http\Controllers
 */

class ProjectApiController extends BaseAPIController
{
    /**
     * @var ProjectRepository
     */

    protected $projectRepo;

    /**
     * @var ProjectService
     */

    protected $projectService;

    /**
     * @var string
     */

    protected $entityType = ENTITY_PROJECT;

    /**
     * ProjectApiController constructor.
     * @param ProjectRepository $projectRepo
     * @param ProjectService $projectService
     */

    public function __construct(ProjectRepository $projectRepo, ProjectService $projectService)
    {
        parent::__construct();

        $this->projectRepo = $projectRepo;
        $this->projectService = $projectService;
    }

    /**
     * @SWG\Get(
     *   path="/projects",
     *   summary="List projects",
     *   operationId="listProjects",
     *   tags={"project"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of projects",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Project"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function index()
    {
        $projects = Project::scope()
            ->withTrashed()
            ->orderBy('created_at', 'desc');

        return $this->listResponse($projects);
    }


    /**
     * @SWG\Get(
     *   path="/projects/{project_id}",
     *   summary="Retrieve a project",
     *   operationId="getProject",
     *   tags={"project"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="project_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single project",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Project"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function show(ProjectRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/projects",
     *   summary="Create a project",
     *   operationId="createProject",
     *   tags={"project"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/project")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New project",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/project"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function store(CreateProjectRequest $request)
    {
        $project = $this->projectService->save($request->input());

        return $this->itemResponse($project);
    }


    /**
     * @SWG\Put(
     *   path="/projects/{project_id}",
     *   summary="Update a project",
     *   operationId="updateProject",
     *   tags={"project"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="project_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="project",
     *     @SWG\Schema(ref="#/definitions/project")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated project",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/project"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $publicId
     */

    public function update(UpdateProjectRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $project = $this->projectService->save($request->input(), $request->entity());

        return $this->itemResponse($project);
    }


    /**
     * @SWG\Delete(
     *   path="/projects/{project_id}",
     *   summary="Delete a project",
     *   operationId="deleteProject",
     *   tags={"project"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="project_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted project",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/project"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     */

     public function destroy(UpdateProjectRequest $request)
     {
         $project = $request->entity();

         $this->projectRepo->delete($project);

         return $this->itemResponse($project);
     }


}
