<?php namespace App\Services;

use URL;
use Utils;
use App\Models\Task;
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

        return $this->createDatatable(ENTITY_TASK, $query, !$clientPublicId);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'client_name',
                function ($model) {
                    return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model)) : '';
                },
                ! $hideClient
            ],
            [
                'created_at',
                function ($model) {
                    return link_to("tasks/{$model->public_id}/edit", Task::calcStartTime($model));
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
                    return !$model->deleted_at || $model->deleted_at == '0000-00-00';
                }
            ],
            [
                trans('texts.view_invoice'),
                function ($model) {
                    return URL::to("/invoices/{$model->invoice_public_id}/edit");
                },
                function ($model) {
                    return $model->invoice_number;
                }
            ],
            [
                trans('texts.stop_task'),
                function ($model) {
                    return "javascript:stopTask({$model->public_id})";
                },
                function ($model) {
                    return $model->is_running;
                }
            ],
            [
                trans('texts.invoice_task'),
                function ($model) {
                    return "javascript:invoiceEntity({$model->public_id})";
                },
                function ($model) {
                    return ! $model->invoice_number && (!$model->deleted_at || $model->deleted_at == '0000-00-00');
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