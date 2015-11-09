<?php namespace App\Http\Controllers;

use Utils;
use Response;
use Input;
use App\Models\Task;
use App\Ninja\Repositories\TaskRepository;

class TaskApiController extends Controller
{
    protected $taskRepo;

    public function __construct(TaskRepository $taskRepo)
    {
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
    public function index($clientPublicId = false)
    {
        $tasks = Task::scope()->with('client');

        if ($clientPublicId) {
            $tasks->whereHas('client', function($query) use ($clientPublicId) {
                $query->where('public_id', '=', $clientPublicId);
            });
        }
        
        $tasks = $tasks->orderBy('created_at', 'desc')->get();
        $tasks = Utils::remapPublicIds($tasks);

        $response = json_encode($tasks, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders(count($tasks));

        return Response::make($response, 200, $headers);
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
        $task = Utils::remapPublicIds([$task]);

        $response = json_encode($task, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();

        return Response::make($response, 200, $headers);
    }

}
