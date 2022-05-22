<?php


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

    public function index()
    {
        return Scheduler::all();
    }


    /**
     * @param \App\Http\Requests\TaskScheduler\CreateScheduledTaskRequest $request
     */
    public function store(CreateScheduledTaskRequest $request)
    {
        $scheduler = new Scheduler();
        return $scheduler->service()->store($scheduler, $request);
    }

    public function show(Scheduler $scheduler): Scheduler
    {
        return $scheduler;
    }

    /**
     * @param \App\Models\Scheduler $scheduler
     * @param \App\Http\Requests\TaskScheduler\UpdateScheduleRequest $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function update(Scheduler $scheduler, UpdateScheduleRequest $request)
    {
        return $scheduler->service()->update($scheduler, $request);
    }

    public function updateJob(Scheduler $scheduler, UpdateScheduledJobRequest $request)
    {
        return $scheduler->service()->updateJob($scheduler, $request);

    }


    public function destroy(Scheduler $scheduler)
    {
        return $scheduler->service()->destroy($scheduler);
    }


}
