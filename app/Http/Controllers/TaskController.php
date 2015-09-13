<?php namespace App\Http\Controllers;

use Auth;
use View;
use URL;
use Utils;
use Input;
use Datatable;
use Validator;
use Redirect;
use Session;
use DropdownButton;
use DateTime;
use DateTimeZone;
use App\Models\Client;
use App\Models\Task;
use App\Ninja\Repositories\TaskRepository;
use App\Ninja\Repositories\InvoiceRepository;

class TaskController extends BaseController
{
    protected $taskRepo;

    public function __construct(TaskRepository $taskRepo, InvoiceRepository $invoiceRepo)
    {
        parent::__construct();

        $this->taskRepo = $taskRepo;
        $this->invoiceRepo = $invoiceRepo;
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

        return $table->addColumn('created_at', function($model) { return link_to("tasks/{$model->public_id}/edit", Task::calcStartTime($model)); })
                ->addColumn('time_log', function($model) { return gmdate('H:i:s', Task::calcDuration($model)); })
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
            'timezone' => Auth::user()->account->timezone ? Auth::user()->account->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => Auth::user()->account->getMomentDateTimeFormat(),
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
        $task = Task::scope($publicId)->with('client', 'invoice')->firstOrFail();

        $actions = [];
        if ($task->invoice) {
            $actions[] = ['url' => URL::to("inovices/{$task->invoice->public_id}/edit"), 'label' => trans("texts.view_invoice")];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("invoice")', 'label' => trans("texts.invoice_task")];

            // check for any open invoices
            $invoices = $task->client_id ? $this->invoiceRepo->findOpenInvoices($task->client_id) : [];

            foreach ($invoices as $invoice) {
                $actions[] = ['url' => 'javascript:submitAction("add_to_invoice", '.$invoice->public_id.')', 'label' => trans("texts.add_to_invoice", ["invoice" => $invoice->invoice_number])];
            }
        }

        $actions[] = DropdownButton::DIVIDER;
        if (!$task->trashed()) {
            $actions[] = ['url' => 'javascript:submitAction("archive")', 'label' => trans('texts.archive_task')];
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans('texts.delete_task')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans('texts.restore_task')];
        }

        $data = [
            'task' => $task,
            'clientPublicId' => $task->client ? $task->client->public_id : 0,
            'method' => 'PUT',
            'url' => 'tasks/'.$publicId,
            'title' => trans('texts.edit_task'),
            'duration' => $task->is_running ? $task->getCurrentDuration() : $task->getDuration(),
            'actions' => $actions,
            'timezone' => Auth::user()->account->timezone ? Auth::user()->account->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => Auth::user()->account->getMomentDateTimeFormat(),
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
        $action = Input::get('action');

        if (in_array($action, ['archive', 'delete', 'invoice', 'restore', 'add_to_invoice'])) {
            return self::bulk();
        }

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
        } else if ($action == 'invoice' || $action == 'add_to_invoice') {
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
                    'startTime' => $task->getStartTime(),
                    'duration' => $task->getHours(),
                ];
            }

            if ($action == 'invoice') {
                return Redirect::to("invoices/create/{$clientPublicId}")->with('tasks', $data);
            } else {
                $invoiceId = Input::get('invoice_id');
                return Redirect::to("invoices/{$invoiceId}/edit")->with('tasks', $data);
            }
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
