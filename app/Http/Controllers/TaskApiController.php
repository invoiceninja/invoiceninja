<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Input;
use App\Models\Task;
use App\Ninja\Repositories\TaskRepository;
use App\Http\Controllers\BaseAPIController;
use App\Ninja\Transformers\TaskTransformer;

class TaskApiController extends BaseAPIController
{
    protected $taskRepo;

    public function __construct(TaskRepository $taskRepo)
    {
        parent::__construct();

        $this->taskRepo = $taskRepo;
    }

    /**
     * @SWG\Get(
     *   path="/tasks",
     *   tags={"task"},
     *   summary="List of tasks",
     *   @SWG\Response(
     *     response=200,
     *     description="A list with tasks",
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
        $paginator = Task::scope();
        $tasks = Task::scope()
                    ->with($this->getIncluded());

        if ($clientPublicId = Input::get('client_id')) {
            $filter = function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            };
            $tasks->whereHas('client', $filter);
            $paginator->whereHas('client', $filter);
        }

        $tasks = $tasks->orderBy('created_at', 'desc')->paginate();
        $paginator = $paginator->paginate();
        $transformer = new TaskTransformer(\Auth::user()->account, Input::get('serializer'));

        $data = $this->createCollection($tasks, $transformer, 'tasks', $paginator);

        return $this->response($data);
    }

    /**
     * @SWG\Post(
     *   path="/tasks",
     *   tags={"task"},
     *   summary="Create a task",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
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

}
