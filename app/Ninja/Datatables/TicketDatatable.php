<?php

namespace App\Ninja\Datatables;

use App\Models\Contact;
use Auth;
use Bootstrapper\Facades\Button;
use Bootstrapper\Facades\Icon;
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

                function ($model) use ($entityType) { $model->entityType = ENTITY_TICKET;
                        if(Auth::user()->can('viewModel', $model)) {
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
                        if(isset($model->client_id))
                            return link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml();
                        else
                            return '';
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
            [
                '',
                function ($model) {$str = 'lah';
                    return '
                            <span class="fa fa-file-o" data-toggle="tooltip" data-placement="bottom" title="1"></span>
                            <span class="fa fa-file-o" data-toggle="tooltip" data-placement="bottom" title="2"></span>
                            <span class="fa fa-file-o" data-toggle="tooltip" data-placement="bottom" title="3"></span>
                            <span class="fa fa-file-o" data-toggle="tooltip" data-placement="bottom" title="4"></span>
                            ';
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
                    function ($model) { $model->entityType = $this->entityType;
                        return Auth::user()->can('viewModel', $model);
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
