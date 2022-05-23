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
use App\Http\Requests\TaskScheduler\UpdateScheduledJobRequest;
use App\Http\Requests\TaskScheduler\UpdateScheduleRequest;
use App\Jobs\Report\ProfitAndLoss;
use App\Models\ScheduledJob;
use App\Models\Scheduler;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Request;

class TaskSchedulerController extends BaseController
{
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
        return Scheduler::all();
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
        return $scheduler->service()->store($scheduler, $request);
    }

    /**
     * @OA\GET(
     *      path="/api/v1/task_scheduler/{scheduler}",
     *      operationId="showTaskScheduler",
     *      tags={"task_scheduler"},
     *      summary="Show given scheduler",
     *      description="Get scheduler with associated job",
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

    public function show(Scheduler $scheduler): Scheduler
    {
        return $scheduler;
    }

    /**
     * @OA\PUT(
     *      path="/api/v1/task_scheduler/{scheduler}",
     *      operationId="updateTaskScheduler",
     *      tags={"task_scheduler"},
     *      summary="Update task scheduler ",
     *      description="Update task scheduler",
     * @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     * @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     * @OA\RequestBody(
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
        return $scheduler->service()->update($scheduler, $request);
    }
    /**
     * @OA\PUT(
     *      path="/api/v1/task_scheduler/{scheduler}/update_job/",
     *      operationId="updateTaskSchedulerJob",
     *      tags={"task_scheduler"},
     *      summary="Update job for a task scheduler ",
     *      description="Update job for a task scheduler | if we are changing action for a job, we should send the request for a new job same as we are creating new scheduler",
     * @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     * @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     * @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/UpdateJobForASchedulerSchema")
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
    public function updateJob(Scheduler $scheduler, UpdateScheduledJobRequest $request)
    {
        return $scheduler->service()->updateJob($scheduler, $request);

    }

    /**
     * @OA\DELETE(
     *      path="/api/v1/task_scheduler/{scheduler}",
     *      operationId="destroyTaskScheduler",
     *      tags={"task_scheduler"},
     *      summary="Destroy Task Scheduler",
     *      description="Destroy task scheduler and its associated job",
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
    public function destroy(Scheduler $scheduler)
    {
        return $scheduler->service()->destroy($scheduler);
    }


}
