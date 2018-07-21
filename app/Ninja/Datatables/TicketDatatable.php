<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class TicketDatatable extends EntityDatatable
{
    public $entityType = ENTITY_TICKET;
    //public $sortCol = 1;

    public function columns()
    {
        $entityType = $this->entityType;

        return [
            [
                trans('ticket_number'),

                function ($model) use ($entityType) {
                        if(Auth::user()->can('view', [ENTITY_TICKET, $model])) {
                            $str = link_to("{$entityType}s/{$model->public_id}/edit", $model->public_id, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                            return $this->addNote($str, $model->private_notes);
                        }
                        else
                            return $model->public_id;
                    },
            ],
            [
                'client_name',
                function ($model) {
                    if(Auth::user()->can('view', [ENTITY_CLIENT, $model]))
                        return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                    else
                        return Utils::getClientDisplayName($model);

                },
                ! $this->hideClient,
            ],
            [
                'contact',
                function ($model) {
                    //return link_to("clients/{$model->public_id}", $model->getContact($model->contact_key) ?: '')->toHtml();
                    return link_to("clients/{$model->public_id}", $model->contact_key ?: '')->toHtml();
                },
            ],
            [
                'subject',
                function ($model) use ($entityType) {
                    return $model->subject;
                },
            ],
            [
                'status',
                function ($model) {
                    return $model->status;
                }
            ],
            [
                'date_created',
                function ($model) {
                    return Utils::fromSqlDateTime($model->created_at);
                }
            ],
        ];
    }

    public function actions()
    {
        return [
                [
                    trans('texts.edit_ticket'),
                    function ($model) {
                        if(Auth::user()->can('edit', [ENTITY_TICKET, $model]))
                            return URL::to("tickets/{$model->public_id}/edit");
                        elseif(Auth::user()->can('view', [ENTITY_TICKET, $model]))
                            return URL::to("tickets/{$model->public_id}");
                    },
                ],
        ];
    }
}
