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

use App\Factory\SchedulerFactory;
use App\Filters\SchedulerFilters;
use App\Http\Requests\TaskScheduler\CreateSchedulerRequest;
use App\Http\Requests\TaskScheduler\DestroySchedulerRequest;
use App\Http\Requests\TaskScheduler\ShowSchedulerRequest;
use App\Http\Requests\TaskScheduler\StoreSchedulerRequest;
use App\Http\Requests\TaskScheduler\UpdateSchedulerRequest;
use App\Models\Scheduler;
use App\Repositories\SchedulerRepository;
use App\Transformers\SchedulerTransformer;
use App\Utils\Traits\MakesHash;

class TaskSchedulerController extends BaseController
{
    use MakesHash;

    protected $entity_type = Scheduler::class;

    protected $entity_transformer = SchedulerTransformer::class;

    public function __construct(protected SchedulerRepository $scheduler_repository)
    {
        parent::__construct();
    }

    public function index(SchedulerFilters $filters)
    {
        $schedulers = Scheduler::filter($filters);

        return $this->listResponse($schedulers);
    }

    public function create(CreateSchedulerRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $scheduler = SchedulerFactory::create($user->company()->id, auth()->user()->id);

        return $this->itemResponse($scheduler);
    }

    public function store(StoreSchedulerRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $scheduler = $this->scheduler_repository->save($request->all(), SchedulerFactory::create($user->company()->id, auth()->user()->id));

        return $this->itemResponse($scheduler);
    }


    public function show(ShowSchedulerRequest $request, Scheduler $scheduler)
    {
        return $this->itemResponse($scheduler);
    }

    public function update(UpdateSchedulerRequest $request, Scheduler $scheduler)
    {
        $this->scheduler_repository->save($request->all(), $scheduler);

        return $this->itemResponse($scheduler);
    }

    public function destroy(DestroySchedulerRequest $request, Scheduler $scheduler)
    {
        $this->scheduler_repository->delete($scheduler);

        return $this->itemResponse($scheduler->fresh());
    }

    public function bulk()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = request()->input('action');

        if (!in_array($action, ['archive', 'restore', 'delete'])) {
            return response()->json(['message' => 'Bulk action does not exist'], 400);
        }

        $ids = request()->input('ids');

        $task_schedulers = Scheduler::withTrashed()->find($this->transformKeys($ids));

        $task_schedulers->each(function ($task_scheduler, $key) use ($action, $user) {
            if ($user->can('edit', $task_scheduler)) {
                $this->scheduler_repository->{$action}($task_scheduler);
            }
        });

        return $this->listResponse(Scheduler::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }
}
