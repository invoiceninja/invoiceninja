<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Factory\TaskStatusFactory;
use App\Filters\TaskStatusFilters;
use App\Http\Requests\TaskStatus\ActionTaskStatusRequest;
use App\Http\Requests\TaskStatus\CreateTaskStatusRequest;
use App\Http\Requests\TaskStatus\DestroyTaskStatusRequest;
use App\Http\Requests\TaskStatus\EditTaskStatusRequest;
use App\Http\Requests\TaskStatus\ShowTaskStatusRequest;
use App\Http\Requests\TaskStatus\StoreTaskStatusRequest;
use App\Http\Requests\TaskStatus\UpdateTaskStatusRequest;
use App\Models\TaskStatus;
use App\Repositories\TaskStatusRepository;
use App\Transformers\TaskStatusTransformer;
use App\Utils\Traits\MakesHash;
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
     * index
     *
     * @param  TaskStatusFilters $filters
     * @return Response
     */
    public function index(TaskStatusFilters $filters)
    {
        $task_status = TaskStatus::filter($filters);

        return $this->listResponse($task_status);
    }


    /**
     * create
     *
     * @param  CreateTaskStatusRequest $request
     * @return Response
     */
    public function create(CreateTaskStatusRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $task_status = TaskStatusFactory::create($user->company()->id, auth()->user()->id);

        return $this->itemResponse($task_status);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreTaskStatusRequest $request  The request
     * @return Response
     *
    */
    public function store(StoreTaskStatusRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $task_status = TaskStatusFactory::create($user->company()->id, auth()->user()->id);
        $task_status->fill($request->all());

        $task_status->save();

        return $this->itemResponse($task_status->fresh());
    }

    /**
     * @param ShowTaskStatusRequest $request
     * @param TaskStatus $task_status
     * @return Response|mixed
     */
    public function show(ShowTaskStatusRequest $request, TaskStatus $task_status)
    {
        return $this->itemResponse($task_status);
    }

    /**
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
     * @return Response
     */
    public function update(UpdateTaskStatusRequest $request, TaskStatus $task_status)
    {

        $task_status->fill($request->all());
        $reorder = $task_status->isDirty('status_order');
        $task_status->save();

        if ($reorder) {
            $this->task_status_repo->reorder($task_status);
        }

        return $this->itemResponse($task_status->fresh());

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyTaskStatusRequest $request
     * @param TaskStatus $task_status
     * @return Response
     *
     * @throws \Exception
     */
    public function destroy(DestroyTaskStatusRequest $request, TaskStatus $task_status)
    {
        $task_status = $this->task_status_repo->delete($task_status);

        return $this->itemResponse($task_status);
    }

    /**
     * Perform bulk actions on the list view.
     * @param  ActionTaskStatusRequest $request
     * @return Response
     */
    public function bulk(ActionTaskStatusRequest $request)
    {
        $action = $request->input('action');

        $ids = $request->input('ids');

        TaskStatus::withTrashed()
                ->company()
                ->whereIn('id', $this->transformKeys($ids))
                ->cursor()
                ->each(function ($task_status, $key) use ($action) {
                    $this->task_status_repo->{$action}($task_status);
                });

        return $this->listResponse(TaskStatus::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }
}
