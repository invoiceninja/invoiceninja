<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Ninja\Repositories\TaskRepository;
use App\Ninja\Transformers\TaskTransformer;
use Auth;
use Input;
use Response;

class TaskApiController extends BaseAPIController
{
    protected $taskRepo;

    protected $entityType = ENTITY_TASK;

    public function __construct(TaskRepository $taskRepo)
    {
        parent::__construct();

        $this->taskRepo = $taskRepo;
    }

    /**
     * @SWG\Get(
     *   path="/tasks",
     *   summary="List tasks",
     *   operationId="listTasks",
     *   tags={"task"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of tasks",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Task"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $tasks = Task::scope()
                        ->withTrashed()
                        ->with('client', 'invoice', 'project')
                        ->orderBy('created_at', 'desc');

        return $this->listResponse($tasks);
    }

    /**
     * @SWG\Get(
     *   path="/tasks/{task_id}",
     *   summary="Retrieve a task",
     *   operationId="getTask",
     *   tags={"task"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="task_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single task",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Task"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(TaskRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/tasks",
     *   summary="Create a task",
     *   operationId="createTask",
     *   tags={"task"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="task",
     *     @SWG\Schema(ref="#/definitions/Task")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New task",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Task"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store()
    {
        $data = Input::all();
        $taskId = isset($data['id']) ? $data['id'] : false;

        if (isset($data['client_id']) && $data['client_id']) {
            $data['client'] = $data['client_id'];
        }

        $task = $this->taskRepo->save($taskId, $data);
        $task = Task::scope($task->public_id)->with('client')->first();

        $transformer = new TaskTransformer(Auth::user()->account, Input::get('serializer'));
        $data = $this->createItem($task, $transformer, 'task');

        return $this->response($data);
    }

    /**
     * @SWG\Put(
     *   path="/tasks/{task_id}",
     *   summary="Update a task",
     *   operationId="updateTask",
     *   tags={"task"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="task_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Task")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Update task",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Task"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function update(UpdateTaskRequest $request)
    {
        $task = $request->entity();

        $task = $this->taskRepo->save($task->public_id, \Illuminate\Support\Facades\Input::all());

        return $this->itemResponse($task);
    }

    /**
     * @SWG\Delete(
     *   path="/tasks/{task_id}",
     *   summary="Delete a task",
     *   operationId="deleteTask",
     *   tags={"task"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="task_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted task",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Task"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateTaskRequest $request)
    {
        $task = $request->entity();

        $this->taskRepo->delete($task);

        return $this->itemResponse($task);
    }
}
