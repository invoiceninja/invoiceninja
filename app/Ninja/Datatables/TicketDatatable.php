<?php

namespace App\Ninja\Datatables;

use App\Models\Contact;
use App\Models\Ticket;
use Auth;
use Illuminate\Support\Facades\Log;
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

                function ($model) use ($entityType) {$ticket = Ticket::scope($model->public_id)->first();
                        if(Auth::user()->can('view', $ticket)) {
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
                    if($model->is_internal == false) {
                        $contact = Contact::getContactByContactKey($model->contact_key);
                        if($contact)
                            $name = $contact->getName();
                        else
                            $name = '';
                        return link_to("clients/{$model->client_public_id}", $name)->toHtml();
                    }
                    elseif($model->is_internal == true && ($model->agent_id > 0))
                        return $model->agent_name. ' (' . trans('texts.internal_ticket') . ')';
                    else
                        return '';
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
                        return URL::to("tickets/{$model->public_id}/edit");
                    },
                    function ($model) {$ticket = Ticket::scope($model->public_id)->first();
                        return Auth::user()->can('view', $ticket);
                    },
                ],
                [
                    '--divider--', function () {
                    return false;
                },
                    function ($model) {
                        return Auth::user()->canCreateOrEdit(ENTITY_TICKET);
                    },
                ],
                [
                    trans('texts.ticket_merge'),
                    function ($model) {
                        return URL::to("tickets/merge/{$model->public_id}");
                    },
                     function ($model) {
                         return (Auth::user()->canCreateOrEdit('edit', [ENTITY_TICKET, $model]) && $model->status_id != TICKET_STATUS_MERGED);
                     }
                ],
                [
                    trans('texts.new_internal_ticket'),
                    function ($model) {
                        return URL::to("tickets/create/{$model->public_id}");
                    },
                    function ($model) {
                        return (Auth::user()->canCreateOrEdit('edit', [ENTITY_TICKET, $model]));
                    }
                ],
        ];
    }
}
