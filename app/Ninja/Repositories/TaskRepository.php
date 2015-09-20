<?php namespace App\Ninja\Repositories;

use Auth;
use Carbon;
use Session;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Activity;
use App\Models\Task;

class TaskRepository
{
    public function find($clientPublicId = null, $filter = null)
    {
        $query = \DB::table('tasks')
                    ->leftJoin('clients', 'tasks.client_id', '=', 'clients.id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'tasks.invoice_id')
                    ->where('tasks.account_id', '=', Auth::user()->account_id)
                    ->where(function ($query) {
                        $query->where('contacts.is_primary', '=', true)
                                ->orWhere('contacts.is_primary', '=', null);
                    })
                    ->where('contacts.deleted_at', '=', null)
                    ->where('clients.deleted_at', '=', null)
                    ->select('tasks.public_id', 'clients.name as client_name', 'clients.public_id as client_public_id', 'contacts.first_name', 'contacts.email', 'contacts.last_name', 'invoices.invoice_status_id', 'tasks.description', 'tasks.is_deleted', 'tasks.deleted_at', 'invoices.invoice_number', 'invoices.public_id as invoice_public_id', 'tasks.is_running', 'tasks.time_log', 'tasks.created_at');

        if ($clientPublicId) {
            $query->where('clients.public_id', '=', $clientPublicId);
        }

        if (!Session::get('show_trash:task')) {
            $query->where('tasks.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('tasks.description', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($publicId, $data)
    {        
        if ($publicId) {
            $task = Task::scope($publicId)->firstOrFail();
        } else {
            $task = Task::createNew();
        }

        if (isset($data['client']) && $data['client']) {
            $task->client_id = Client::getPrivateId($data['client']);
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

        array_multisort($timeLog);

        if (isset($data['action'])) {
            if ($data['action'] == 'start') {
                $task->is_running = true;
                $timeLog[] = [strtotime('now'), false];
            } else if ($data['action'] == 'resume') {
                $task->is_running = true;
                $timeLog[] = [strtotime('now'), false];
            } else if ($data['action'] == 'stop' && $task->is_running) {
                $timeLog[count($timeLog)-1][1] = time();
                $task->is_running = false;
            }
        }

        $task->time_log = json_encode($timeLog);
        $task->save();

        return $task;
    }

    public function bulk($ids, $action)
    {
        $tasks = Task::withTrashed()->scope($ids)->get();

        foreach ($tasks as $task) {
            if ($action == 'restore') {
                $task->restore();

                $task->is_deleted = false;
                $task->save();
            } else {
                if ($action == 'delete') {
                    $task->is_deleted = true;
                    $task->save();
                }

                $task->delete();
            }
        }

        return count($tasks);
    }
}
