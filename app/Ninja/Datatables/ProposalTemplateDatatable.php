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
                'name',
                function ($model) {
                    if (! Auth::user()->can('editByOwner', [ENTITY_PROPOSAL_TEMPLATE, $model->user_id])) {
                        return $model->name;
                    }

                    return link_to("proposals/templates/{$model->public_id}", $model->name)->toHtml();
                    //$str = link_to("quotes/{$model->quote_public_id}", $model->quote_number)->toHtml();
                    //return $this->addNote($str, $model->private_notes);
                },
            ],
            [
                'content',
                function ($model) {
                    return $this->showWithTooltip(strip_tags($model->content));
                },
            ],
            [
                'private_notes',
                function ($model) {
                    return $this->showWithTooltip($model->private_notes);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_proposal_template'),
                function ($model) {
                    return URL::to("proposals/templates/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PROPOSAL_TEMPLATE, $model->user_id]);
                },
            ],
            [
                trans('texts.clone_proposal_template'),
                function ($model) {
                    return URL::to("proposals/templates/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PROPOSAL_TEMPLATE, $model->user_id]);
                },
            ],
            [
                trans('texts.new_proposal'),
                function ($model) {
                    return URL::to("proposals/create/0/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', [ENTITY_PROPOSAL, $model->user_id]);
                },
            ],
        ];
    }
}
