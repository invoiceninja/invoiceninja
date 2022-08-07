<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\TaskScheduler\CreateScheduledTaskRequest;
use App\Http\Requests\TaskScheduler\UpdateScheduleRequest;
use App\Jobs\Ninja\TaskScheduler;
use App\Jobs\Report\ProfitAndLoss;
use App\Models\Scheduler;
use App\Repositories\TaskSchedulerRepository;
use App\Transformers\TaskSchedulerTransformer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Request;

class TaskSchedulerController extends BaseController
{
    protected $entity_type = Scheduler::class;

    protected $entity_transformer = TaskSchedulerTransformer::class;

    protected TaskSchedulerRepository $scheduler_repository;

    public function __construct(TaskSchedulerRepository $scheduler_repository)
    {
        parent::__construct();

        $this->scheduler_repository = $scheduler_repository;
    }

    /**
     * @OA\GET(
     *      path="/api/v1/task_scheduler/",
     *      operationId="getTaskSchedulers",
     *      tags={"task_scheduler"},
     *      summary="Task Scheduler Index",
     *      description="Get all schedulers with associated jobs",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
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
    public function index()
    {
        $schedulers = Scheduler::where('company_id', auth()->user()->company()->id);

        return $this->listResponse($schedulers);
    }

    /**
     * @OA\Post(
     *      path="/api/v1/task_scheduler/",
     *      operationId="createTaskScheduler",
     *      tags={"task_scheduler"},
     *      summary="Create task scheduler with job ",
     *      description="Create task scheduler with a job (action(job) request should be sent via request also. Example: We want client report to be job which will be run
     * multiple times, we should send the same parameters in the request as we would send if we wanted to get report, see example",
     * @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
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
    public function store(CreateScheduledTaskRequest $request)
    {
        $scheduler = new Scheduler();
        $scheduler->service()->store($scheduler, $request);

        return $this->itemResponse($scheduler);
    }

    /**
     * @OA\GET(
     *      path="/api/v1/task_scheduler/{id}",
     *      operationId="showTaskScheduler",
     *      tags={"task_scheduler"},
     *      summary="Show given scheduler",
     *      description="Get scheduler with associated job",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
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
    public function show(Scheduler $scheduler)
    {
        return $this->itemResponse($scheduler);
    }

    /**
     * @OA\PUT(
     *      path="/api/v1/task_scheduler/{id}",
     *      operationId="updateTaskScheduler",
     *      tags={"task_scheduler"},
     *      summary="Update task scheduler ",
     *      description="Update task scheduler",
     * @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
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
     *          @OA\JsonContent(ref="#/components/schemas/UpdateTaskSchedulerSchema")
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
    public function update(Scheduler $scheduler, UpdateScheduleRequest $request)
    {
        $scheduler->service()->update($scheduler, $request);

        return $this->itemResponse($scheduler);
    }

    /**
     * @OA\DELETE(
     *      path="/api/v1/task_scheduler/{id}",
     *      operationId="destroyTaskScheduler",
     *      tags={"task_scheduler"},
     *      summary="Destroy Task Scheduler",
     *      description="Destroy task scheduler and its associated job",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
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
    public function destroy(Scheduler $scheduler)
    {
        $this->scheduler_repository->delete($scheduler);

        return $this->itemResponse($scheduler->fresh());
    }
}
