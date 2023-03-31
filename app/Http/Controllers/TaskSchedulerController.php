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
use Symfony\Component\HttpFoundation\Request;

class TaskSchedulerController extends BaseController
{
    use MakesHash;

    protected $entity_type = Scheduler::class;

    protected $entity_transformer = SchedulerTransformer::class;

    public function __construct(protected SchedulerRepository $scheduler_repository)
    {
        parent::__construct();
    }

    /**
     * @OA\GET(
     *      path="/api/v1/task_schedulers/",
     *      operationId="getTaskSchedulers",
     *      tags={"task_schedulers"},
     *      summary="Task Scheduler Index",
     *      description="Get all schedulers with associated jobs",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function index(SchedulerFilters $filters)
    {
        $schedulers = Scheduler::filter($filters);

        return $this->listResponse($schedulers);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateSchedulerRequest $request  The request
     *
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/invoices/task_schedulers",
     *      operationId="getTaskScheduler",
     *      tags={"task_schedulers"},
     *      summary="Gets a new blank scheduler object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank scheduler object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskSchedulerSchema"),
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
    public function create(CreateSchedulerRequest $request)
    {
        $scheduler = SchedulerFactory::create(auth()->user()->company()->id, auth()->user()->id);

        return $this->itemResponse($scheduler);
    }

    /**
     * @OA\Post(
     *      path="/api/v1/task_schedulers/",
     *      operationId="createTaskScheduler",
     *      tags={"task_schedulers"},
     *      summary="Create task scheduler with job ",
     *      description="Create task scheduler with a job (action(job) request should be sent via request also. Example: We want client report to be job which will be run
     * multiple times, we should send the same parameters in the request as we would send if we wanted to get report, see example",
     * @OA\Parameter(ref="#/components/parameters/X-API-SECRET"),
     * @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     * @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/TaskSchedulerSchema")
     *      ),
     * @OA\Response(
     *          response=200,
     *          description="success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     * @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     * @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreSchedulerRequest $request)
    {
        $scheduler = $this->scheduler_repository->save($request->all(), SchedulerFactory::create(auth()->user()->company()->id, auth()->user()->id));

        return $this->itemResponse($scheduler);
    }

    /**
     * @OA\GET(
     *      path="/api/v1/task_schedulers/{id}",
     *      operationId="showTaskScheduler",
     *      tags={"task_schedulers"},
     *      summary="Show given scheduler",
     *      description="Get scheduler with associated job",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Scheduler Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowSchedulerRequest $request, Scheduler $scheduler)
    {
        return $this->itemResponse($scheduler);
    }

    /**
     * @OA\PUT(
     *      path="/api/v1/task_schedulers/{id}",
     *      operationId="updateTaskScheduler",
     *      tags={"task_schedulers"},
     *      summary="Update task scheduler ",
     *      description="Update task scheduler",
     * @OA\Parameter(ref="#/components/parameters/X-API-SECRET"),
     * @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Scheduler Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),     * @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/TaskSchedulerSchema")
     *      ),
     * @OA\Response(
     *          response=200,
     *          description="success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     * @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     * @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateSchedulerRequest $request, Scheduler $scheduler)
    {
        $this->scheduler_repository->save($request->all(), $scheduler);

        return $this->itemResponse($scheduler);
    }

    /**
     * @OA\DELETE(
     *      path="/api/v1/task_schedulers/{id}",
     *      operationId="destroyTaskScheduler",
     *      tags={"task_schedulers"},
     *      summary="Destroy Task Scheduler",
     *      description="Destroy task scheduler and its associated job",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Scheduler Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroySchedulerRequest $request, Scheduler $scheduler)
    {
        $this->scheduler_repository->delete($scheduler);

        return $this->itemResponse($scheduler->fresh());
    }


    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/task_schedulers/bulk",
     *      operationId="bulkTaskSchedulerActions",
     *      tags={"task_schedulers"},
     *      summary="Performs bulk actions on an array of task_schedulers",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="array of ids",
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
     *          description="The TaskSchedule response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaskSchedulerSchema"),
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

        if (!in_array($action, ['archive', 'restore', 'delete'])) {
            return response()->json(['message' => 'Bulk action does not exist'], 400);
        }

        $ids = request()->input('ids');

        $task_schedulers = Scheduler::withTrashed()->find($this->transformKeys($ids));

        $task_schedulers->each(function ($task_scheduler, $key) use ($action) {
            if (auth()->user()->can('edit', $task_scheduler)) {
                $this->scheduler_repository->{$action}($task_scheduler);
            }
        });

        return $this->listResponse(Scheduler::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }
}
