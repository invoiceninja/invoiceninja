<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\Project;
use App\Models\Client;

class TaskKanbanController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $tasks = Task::scope()->with(['project', 'client', 'task_status'])->orderBy('task_status_sort_order')->get();
        $stauses = TaskStatus::scope()->orderBy('sort_order')->get();
        $projects = Project::scope()->get();
        $clients = Client::scope()->get();

        // check initial statuses exist
        if (! $stauses->count()) {
            $stauses = [];
            $defaults = [
                'backlog',
                'ready_to_do',
                'in_progress',
                'done',
            ];
            for ($i=0; $i<count($defaults); $i++) {
                $status = TaskStatus::createNew();
                $status->name = trans('texts.' . $defaults[$i]);
                $status->sort_order = $i;
                $status->save();
                $stauses[] = $status;
            }
        }

        $data = [
            'title' => trans('texts.kanban'),
            'statuses' => $stauses,
            'tasks' => $tasks,
            'clients' => $clients,
            'projects' => $projects,
        ];

        return view('tasks.kanban', $data);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeStatus()
    {
        return $this->saveStatus();
    }

    /**
     * @param $publicId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStatus($publicId)
    {
        return $this->saveStatus($publicId);
    }

    /**
     * @param bool $publicId
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    private function saveStatus($publicId = false)
    {
        if ($publicId) {
            $status = TaskStatus::scope($publicId)->firstOrFail();
        } else {
            $status = TaskStatus::createNew();
        }

        $status->fill(request()->all());
        $status->save();

        return response()->json($status);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteStatus($publicId)
    {
        $status = TaskStatus::scope($publicId)->first();

        if ($status) {
            $status->delete();
        }

        return response()->json(['message' => RESULT_SUCCESS]);
    }

}
