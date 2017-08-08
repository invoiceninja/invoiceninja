<?php

namespace App\Ninja\Repositories;

use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use Auth;
use Session;
use DB;

class TaskRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Task';
    }

    public function find($clientPublicId = null, $filter = null)
    {
        $query = DB::table('tasks')
                    ->leftJoin('clients', 'tasks.client_id', '=', 'clients.id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'tasks.invoice_id')
                    ->leftJoin('projects', 'projects.id', '=', 'tasks.project_id')
                    ->where('tasks.account_id', '=', Auth::user()->account_id)
                    ->where(function ($query) { // handle when client isn't set
                        $query->where('contacts.is_primary', '=', true)
                                ->orWhere('contacts.is_primary', '=', null);
                    })
                    ->where('contacts.deleted_at', '=', null)
                    ->select(
                        'tasks.public_id',
                        DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                        'clients.public_id as client_public_id',
                        'clients.user_id as client_user_id',
                        'contacts.first_name',
                        'contacts.email',
                        'contacts.last_name',
                        'invoices.invoice_status_id',
                        'tasks.description',
                        'tasks.is_deleted',
                        'tasks.deleted_at',
                        'invoices.invoice_number',
                        'invoices.invoice_number as status',
                        'invoices.public_id as invoice_public_id',
                        'invoices.user_id as invoice_user_id',
                        'invoices.balance',
                        'tasks.is_running',
                        'tasks.time_log',
                        'tasks.time_log as duration',
                        'tasks.created_at',
                        DB::raw("SUBSTRING(time_log, 3, 10) date"),
                        'tasks.user_id',
                        'projects.name as project',
                        'projects.public_id as project_public_id',
                        'projects.user_id as project_user_id'
                    );

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        } else {
            $query->whereNull('clients.deleted_at');
        }

        $this->applyFilters($query, ENTITY_TASK);

        if ($statuses = session('entity_status_filter:' . ENTITY_TASK)) {
            $statuses = explode(',', $statuses);
            $query->where(function ($query) use ($statuses) {
                if (in_array(TASK_STATUS_LOGGED, $statuses)) {
                    $query->orWhere('tasks.invoice_id', '=', 0)
                          ->orWhereNull('tasks.invoice_id');
                }
                if (in_array(TASK_STATUS_RUNNING, $statuses)) {
                    $query->orWhere('tasks.is_running', '=', 1);
                }
                if (in_array(TASK_STATUS_INVOICED, $statuses)) {
                    $query->orWhere('tasks.invoice_id', '>', 0);
                    if (! in_array(TASK_STATUS_PAID, $statuses)) {
                        $query->where('invoices.balance', '>', 0);
                    }
                }
                if (in_array(TASK_STATUS_PAID, $statuses)) {
                    $query->orWhere('invoices.balance', '=', 0);
                }
            });
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('tasks.description', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%')
                      ->orWhere('projects.name', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($publicId, $data, $task = null)
    {
        if ($task) {
            // do nothing
        } elseif ($publicId) {
            $task = Task::scope($publicId)->withTrashed()->firstOrFail();
        } else {
            $task = Task::createNew();
        }

        if ($task->is_deleted) {
            return $task;
        }

        if (isset($data['client'])) {
            $task->client_id = $data['client'] ? Client::getPrivateId($data['client']) : null;
        }
        if (isset($data['project_id'])) {
            $task->project_id = $data['project_id'] ? Project::getPrivateId($data['project_id']) : null;
        }

        if (isset($data['description'])) {
            $task->description = trim($data['description']);
        }

        if (isset($data['time_log'])) {
            $timeLog = json_decode($data['time_log']);
        } elseif ($task->time_log) {
            $timeLog = json_decode($task->time_log);
        } else {
            $timeLog = [];
        }

        if(isset($data['client_id'])) {
            $task->client_id = Client::getPrivateId($data['client_id']);
        }

        array_multisort($timeLog);

        if (isset($data['action'])) {
            if ($data['action'] == 'start') {
                $task->is_running = true;
                $timeLog[] = [strtotime('now'), false];
            } elseif ($data['action'] == 'resume') {
                $task->is_running = true;
                $timeLog[] = [strtotime('now'), false];
            } elseif ($data['action'] == 'stop' && $task->is_running) {
                $timeLog[count($timeLog) - 1][1] = time();
                $task->is_running = false;
            } elseif ($data['action'] == 'offline'){
                $task->is_running = $data['is_running'] ? 1 : 0;
            }
        }

        $task->time_log = json_encode($timeLog);
        $task->save();

        return $task;
    }
}
