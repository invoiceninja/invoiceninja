<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class ProposalTemplateDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PROPOSAL_TEMPLATE;
    public $sortCol = 1;

    public function columns()
    {
        return [
            [
                'quote',
                function ($model) {
                    if (! Auth::user()->can('editByOwner', [ENTITY_PROPOSAL_TEMPLATE, $model->user_id])) {
                        return $model->name;
                    }

                    return link_to("proposal_templates/{$model->public_id}", $model->name)->toHtml();
                    //$str = link_to("quotes/{$model->quote_public_id}", $model->quote_number)->toHtml();
                    //return $this->addNote($str, $model->private_notes);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_template'),
                function ($model) {
                    return URL::to("proposal_templates/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PROPOSAL_TEMPLATE, $model->user_id]);
                },
            ],
        ];
    }
}
