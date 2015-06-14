<?php namespace App\Http\Controllers;

use View;
use URL;
use Utils;
use Input;
use Datatable;
use Validator;
use Redirect;
use Session;
use App\Models\Client;
use App\Models\Task;

/*
use Auth;
use Cache;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Size;
use App\Models\PaymentTerm;
use App\Models\Industry;
use App\Models\Currency;
use App\Models\Country;
*/

use App\Ninja\Repositories\TaskRepository;

class TaskController extends BaseController
{
    protected $taskRepo;

    public function __construct(TaskRepository $taskRepo)
    {
        parent::__construct();

        $this->taskRepo = $taskRepo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {        
        return View::make('list', array(
            'entityType' => ENTITY_TASK,
            'title' => trans('texts.tasks'),
            'sortCol' => '2',
            'columns' => Utils::trans(['checkbox', 'client', 'date', 'duration', 'description', 'status', 'action']),
        ));
    }

    public function getDatatable($clientPublicId = null)
    {
        $tasks = $this->taskRepo->find($clientPublicId, Input::get('sSearch'));

        $table = Datatable::query($tasks);

        if (!$clientPublicId) {
            $table->addColumn('checkbox', function ($model) { return '<input type="checkbox" name="ids[]" value="'.$model->public_id.'" '.Utils::getEntityRowClass($model).'>'; })
                  ->addColumn('client_name', function ($model) { return $model->client_public_id ? link_to('clients/'.$model->client_public_id, Utils::getClientDisplayName($model)) : ''; });
        }

        return $table->addColumn('start_time', function($model) { return Utils::fromSqlDateTime($model->start_time); })
                ->addColumn('duration', function($model) { return gmdate('H:i:s', $model->is_running ? time() - strtotime($model->start_time) : $model->duration); })
                ->addColumn('description', function($model) { return $model->description; })
                ->addColumn('invoice_number', function($model) { return self::getStatusLabel($model); })
                ->addColumn('dropdown', function ($model) {
                    $str = '<div class="btn-group tr-action" style="visibility:hidden;">
      							<button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
        							'.trans('texts.select').' <span class="caret"></span>
      							</button>
      							<ul class="dropdown-menu" role="menu">';

                        if (!$model->deleted_at || $model->deleted_at == '0000-00-00') {
                            $str .= '<li><a href="'.URL::to('tasks/'.$model->public_id.'/edit').'">'.trans('texts.edit_task').'</a></li>';
                        }

                        if ($model->invoice_number) {
                            $str .= '<li>' . link_to("/invoices/{$model->invoice_public_id}/edit", trans('texts.view_invoice')) . '</li>';
                        } elseif ($model->is_running) {
                            $str .= '<li><a href="javascript:stopTask('.$model->public_id.')">'.trans('texts.stop_task').'</a></li>';
                        } elseif (!$model->deleted_at || $model->deleted_at == '0000-00-00') {
                            $str .= '<li><a href="javascript:invoiceTask('.$model->public_id.')">'.trans('texts.invoice_task').'</a></li>';
                        }

                        if (!$model->deleted_at || $model->deleted_at == '0000-00-00') {
    						$str .= '<li class="divider"></li>
    						    <li><a href="javascript:archiveEntity('.$model->public_id.')">'.trans('texts.archive_task').'</a></li>';
                        } else {
                            $str .= '<li><a href="javascript:restoreEntity('.$model->public_id.')">'.trans('texts.restore_task').'</a></li>';
                        }

                        if (!$model->is_deleted) {
                            $str .= '<li><a href="javascript:deleteEntity('.$model->public_id.')">'.trans('texts.delete_task').'</a></li></ul>';
                        }

                        return $str . '</div>';
                })
                ->make();
    }

    private function getStatusLabel($model) {
        if ($model->invoice_number) {
            $class = 'success';
            $label = trans('texts.invoiced');
        } elseif ($model->is_running) {
            $class = 'primary';
            $label = trans('texts.running');
        } else {
            $class = 'default';
            $label = trans('texts.logged');
        }
        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }


    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        return $this->save();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create($clientPublicId = 0)
    {
        $data = [
            'task' => null,
            'clientPublicId' => Input::old('client') ? Input::old('client') : $clientPublicId,
            'method' => 'POST',
            'url' => 'tasks',
            'title' => trans('texts.new_task'),
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('tasks.edit', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function edit($publicId)
    {
        $task = Task::scope($publicId)->with('client')->firstOrFail();
        
        $data = [
            'task' => $task,
            'clientPublicId' => $task->client ? $task->client->public_id : 0,
            'method' => 'PUT',
            'url' => 'tasks/'.$publicId,
            'title' => trans('texts.edit_task')
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('tasks.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update($publicId)
    {
        return $this->save($publicId);
    }

    private static function getViewModel()
    {
        return [
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get()
        ];
    }

    private function save($publicId = null)
    {
        $task = $this->taskRepo->save($publicId, Input::all());

        Session::flash('message', trans($publicId ? 'texts.updated_task' : 'texts.created_task'));

        return Redirect::to("tasks/{$task->public_id}/edit");
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('id') ? Input::get('id') : Input::get('ids');

        if ($action == 'stop') {
            $this->taskRepo->save($ids, ['action' => $action]);
            Session::flash('message', trans('texts.stopped_task'));
            return Redirect::to('tasks');
        } else if ($action == 'invoice') {

            $tasks = Task::scope($ids)->with('client')->get();
            $clientPublicId = false;
            $data = [];

            foreach ($tasks as $task) {
                if ($task->client) {
                    if (!$clientPublicId) {
                        $clientPublicId = $task->client->public_id;
                    } else if ($clientPublicId != $task->client->public_id) {
                        Session::flash('error', trans('texts.task_error_multiple_clients'));
                        return Redirect::to('tasks');
                    }
                }

                if ($task->is_running) {
                    Session::flash('error', trans('texts.task_error_running'));
                    return Redirect::to('tasks');
                } else if ($task->invoice_id) {
                    Session::flash('error', trans('texts.task_error_invoiced'));
                    return Redirect::to('tasks');
                }

                $data[] = [
                    'publicId' => $task->public_id,
                    'description' => $task->description,
                    'startTime' => Utils::fromSqlDateTime($task->start_time),
                    'duration' => round($task->duration / (60 * 60), 2)
                ];
            }

            return Redirect::to("invoices/create/{$clientPublicId}")->with('tasks', $data);
        } else {
            $count = $this->taskRepo->bulk($ids, $action);

            $message = Utils::pluralize($action.'d_task', $count);
            Session::flash('message', $message);

            if ($action == 'restore' && $count == 1) {
                return Redirect::to('tasks/'.$ids[0].'/edit');
            } else {
                return Redirect::to('tasks');
            }
        }
    }
}
