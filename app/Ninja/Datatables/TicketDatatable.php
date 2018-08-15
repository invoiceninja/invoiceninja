<?php

namespace App\Ninja\Datatables;

use App\Models\Contact;
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
                            $str = link_to("{$entityType}s/{$model->public_id}/edit", $model->ticket_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                            return $this->addNote($str, $model->private_notes);
                        }
                        else
                            return $model->ticket_number;
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
                    if($model->contact_key)
                        return link_to("clients/{$model->client_public_id}", Contact::getContactByContactKey($model->contact_key)->getName() ?: '')->toHtml();
                    elseif($model->is_internal && $model->agent_id)
                        return $model->agent_name. ' (' . trans('texts.internal_ticket') . ')';
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
                    return $model->merged_parent_ticket_id ? trans('texts.merged') : $model->status;
                }
            ],
            [
                'due_date',
                function ($model) {
                    return Utils::fromSqlDateTime($model->due_date) ?: trans('texts.no_due_date');
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
