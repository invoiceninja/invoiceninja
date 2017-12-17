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
        $tasks = Task::scope()->with(['project', 'client'])->get();
        $projects = Project::scope()->get();
        $clients = Client::scope()->get();
        $stauses = TaskStatus::scope()->get();

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

}
