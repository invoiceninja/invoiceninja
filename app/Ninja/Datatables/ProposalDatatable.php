<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class ProposalDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PROPOSAL;
    public $sortCol = 1;

    public function columns()
    {
        return [
            [
                'quote',
                function ($model) {
                    if (! Auth::user()->can('viewByOwner', [ENTITY_QUOTE, $model->user_id])) {
                        return $model->quote_number;
                    }

                    return link_to("quotes/{$model->quote_public_id}", $model->quote_number)->toHtml();
                    //$str = link_to("quotes/{$model->quote_public_id}", $model->quote_number)->toHtml();
                    //return $this->addNote($str, $model->private_notes);
                },
            ],
            [
                'template',
                function ($model) {
                    return $model->template_name;
                },
            ],
            [
                'created',
                function ($model) {
                    return Utils::fromSqlDate($model->created_at);
                },
            ],
            [
                'valid_until',
                function ($model) {
                    return Utils::fromSqlDate($model->due_date);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_proposal'),
                function ($model) {
                    return URL::to("proposals/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PROPOSAL, $model->user_id]);
                },
            ],
        ];
    }
}
