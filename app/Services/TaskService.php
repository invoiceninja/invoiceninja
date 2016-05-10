<?php namespace App\Services;

use Auth;
use URL;
use Utils;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\Client;
use App\Ninja\Repositories\TaskRepository;
use App\Services\BaseService;

class TaskService extends BaseService
{
    protected $datatableService;
    protected $taskRepo;

    public function __construct(TaskRepository $taskRepo, DatatableService $datatableService)
    {
        $this->taskRepo = $taskRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->taskRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($clientPublicId, $search)
    {
        $query = $this->taskRepo->find($clientPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('tasks.user_id', '=', Auth::user()->id);
        }

        return $this->createDatatable(ENTITY_TASK, $query, !$clientPublicId);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'client_name',
                function ($model) {
                    if(!Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])){
                        return Utils::getClientDisplayName($model);
                    }
                    
                    return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml() : '';
                },
                ! $hideClient
            ],
            [
                'created_at',
                function ($model) {
                    return link_to("tasks/{$model->public_id}/edit", Task::calcStartTime($model))->toHtml();
                }
            ],
            [
                'time_log',
                function($model) {
                    return Utils::formatTime(Task::calcDuration($model));
                }
            ],
            [
                'description',
                function ($model) {
                    return $model->description;
                }
            ],
            [
                'invoice_number',
                function ($model) {
                    return self::getStatusLabel($model);
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_task'),
                function ($model) {
                    return URL::to('tasks/'.$model->public_id.'/edit');
                },
                function ($model) {
                    return (!$model->deleted_at || $model->deleted_at == '0000-00-00') && Auth::user()->can('editByOwner', [ENTITY_TASK, $model->user_id]);
                }
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("/invoices/{$model->invoice_public_id}/edit");
                },
                function ($model) {
                    return $model->invoice_number && Auth::user()->can('editByOwner', [ENTITY_INVOICE, $model->invoice_user_id]);
                }
            ],
            [
                trans('texts.stop_task'),
                function ($model) {
                    return "javascript:stopTask({$model->public_id})";
                },
                function ($model) {
                    return $model->is_running && Auth::user()->can('editByOwner', [ENTITY_TASK, $model->user_id]);
                }
            ],
            [
                trans('texts.invoice_task'),
                function ($model) {
                    return "javascript:invoiceEntity({$model->public_id})";
                },
                function ($model) {
                    return ! $model->invoice_number && (!$model->deleted_at || $model->deleted_at == '0000-00-00') && Auth::user()->can('create', ENTITY_INVOICE);
                }
            ]
        ];
    }

    private function getStatusLabel($model)
    {
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

}