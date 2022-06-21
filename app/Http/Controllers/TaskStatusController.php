<?php

namespace App\Http\Controllers;

use App\Factory\TaskStatusFactory;
use App\Filters\TaskStatusFilters;
use App\Http\Requests\TaskStatus\CreateTaskStatusRequest;
use App\Http\Requests\TaskStatus\DestroyTaskStatusRequest;
use App\Http\Requests\TaskStatus\ShowTaskStatusRequest;
use App\Http\Requests\TaskStatus\StoreTaskStatusRequest;
use App\Http\Requests\TaskStatus\UpdateTaskStatusRequest;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Repositories\TaskStatusRepository;
use App\Transformers\TaskStatusTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskStatusController extends BaseController
{
    use MakesHash;

    protected $entity_type = TaskStatus::class;

    protected $entity_transformer = TaskStatusTransformer::class;

    /**
     * @var TaskStatusRepository
     */
    protected $task_status_repo;

    /**
     * TaskStatusController constructor.
     *
     * @param TaskStatusRepository $task_status_repo  The payment term repo
     */
    public function __construct(TaskStatusRepository $task_status_repo)
    {
        parent::__construct();

        $this->task_status_repo = $task_status_repo;
    }

    /**
     *      @OA\Get(
     *      path="/api/v1/task_statuses",
     *      operationId="getTaskStatuses",
     *      tags={"task_status"},
     *      summary="Gets a list of task statuses",
     *      description="Lists task statuses",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of task statuses",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskStatus"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function index(TaskStatusFilters $filters)
    {
        $task_status = TaskStatus::filter($filters);

        return $this->listResponse($task_status);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateTaskStatusRequest $request  The request
     *
     * @return Response
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/task_statuses/create",
     *      operationId="getTaskStatussCreate",
     *      tags={"task_status"},
     *      summary="Gets a new blank TaskStatus object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank TaskStatus object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskStatus"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateTaskStatusRequest $request)
    {
        $task_status = TaskStatusFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($task_status);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreTaskStatusRequest $request  The request
     *
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/task_statuses",
     *      operationId="storeTaskStatus",
     *      tags={"task_status"},
     *      summary="Adds a TaskStatus",
     *      description="Adds a TaskStatusto the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *         description="The task_status request",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/TaskStatus"),
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved TaskStatus object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskStatus"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreTaskStatusRequest $request)
    {
        // nlog($request->all());

        $task_status = TaskStatusFactory::create(auth()->user()->company()->id, auth()->user()->id);
        $task_status->fill($request->all());

        $task_status->save();

        return $this->itemResponse($task_status->fresh());
    }

    /**
     * @OA\Get(
     *      path="/api/v1/task_statuses/{id}",
     *      operationId="showTaskStatus",
     *      tags={"task_status"},
     *      summary="Shows a TaskStatus Term",
     *      description="Displays an TaskStatusby id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The TaskStatusHashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the TaskStatusobject",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskStatus"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param ShowTaskStatusRequest $request
     * @param TaskStatus $task_status
     * @return Response|mixed
     */
    public function show(ShowTaskStatusRequest $request, TaskStatus $task_status)
    {
        return $this->itemResponse($task_status);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/task_statuses/{id}/edit",
     *      operationId="editTaskStatuss",
     *      tags={"task_status"},
     *      summary="Shows an TaskStatusfor editting",
     *      description="Displays an TaskStatusby id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The TaskStatusHashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the TaskStatus object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskStatus"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param EditTaskStatusRequest $request
     * @param TaskStatus $payment
     * @return Response|mixed
     */
    public function edit(EditTaskStatusRequest $request, TaskStatus $payment)
    {
        return $this->itemResponse($payment);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTaskStatusRequest $request  The request
     * @param TaskStatus $task_status   The payment term
     *
     * @return Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/task_statuses/{id}",
     *      operationId="updateTaskStatus",
     *      tags={"task_status"},
     *      summary="Updates a TaskStatus Term",
     *      description="Handles the updating of an TaskStatus Termby id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The TaskStatusHashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the TaskStatusobject",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskStatus"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateTaskStatusRequest $request, TaskStatus $task_status)
    {
        $task_status->fill($request->all());
        $task_status->save();

        return $this->itemResponse($task_status->fresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyTaskStatusRequest $request
     * @param TaskStatus $task_status
     *
     * @return     Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/task_statuses/{id}",
     *      operationId="deleteTaskStatus",
     *      tags={"task_statuss"},
     *      summary="Deletes a TaskStatus Term",
     *      description="Handles the deletion of an TaskStatus by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The TaskStatusHashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyTaskStatusRequest $request, TaskStatus $task_status)
    {
        $task_status = $this->task_status_repo->delete($task_status);

        return $this->itemResponse($task_status);
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Collection
     *
     *
     * @OA\Post(
     *      path="/api/v1/task_statuses/bulk",
     *      operationId="bulkTaskStatuss",
     *      tags={"task_status"},
     *      summary="Performs bulk actions on an array of task statuses",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="TaskStatus Ter,s",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The TaskStatus Terms response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskStatus"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');

        $task_status = TaskStatus::withTrashed()->company()->find($this->transformKeys($ids));

        $task_status->each(function ($task_status, $key) use ($action) {
            if (auth()->user()->can('edit', $task_status)) {
                $this->task_status_repo->{$action}($task_status);
            }
        });

        return $this->listResponse(TaskStatus::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }
}
