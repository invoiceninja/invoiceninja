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
                    if (Auth::user()->can('view', [ENTITY_PROPOSAL_TEMPLATE, $model]))
                        return link_to("proposals/templates/{$model->public_id}", $model->name)->toHtml();
                    else
                        return $model->name;
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
                    return Auth::user()->can('view', [ENTITY_PROPOSAL_TEMPLATE, $model]);
                },
            ],
            [
                trans('texts.clone_proposal_template'),
                function ($model) {
                    return URL::to("proposals/templates/{$model->public_id}/clone");
                },
                function ($model) {
                    return Auth::user()->can('view', [ENTITY_PROPOSAL_TEMPLATE, $model]);
                },
            ],
            [
                trans('texts.new_proposal'),
                function ($model) {
                    return URL::to("proposals/create/0/{$model->public_id}");
                },
                function ($model) {
                    return Auth::user()->can('create', [ENTITY_PROPOSAL, $model]);
                },
            ],
        ];
    }
}
