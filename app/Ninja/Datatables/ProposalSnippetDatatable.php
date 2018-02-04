<?php

namespace App\Ninja\Datatables;

use Auth;
use URL;
use Utils;

class ProposalSnippetDatatable extends EntityDatatable
{
    public $entityType = ENTITY_PROPOSAL_SNIPPET;
    public $sortCol = 1;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    if (! Auth::user()->can('editByOwner', [ENTITY_PROPOSAL_SNIPPET, $model->user_id])) {
                        return $model->name;
                    }

                    return link_to("proposal_snippets/{$model->public_id}", $model->name)->toHtml();
                    //$str = link_to("proposal_snippets/{$model->public_id}", $model->name)->toHtml();
                    //return $this->addNote($str, $model->private_notes);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_snippet'),
                function ($model) {
                    return URL::to("proposals_snippets/{$model->public_id}/edit");
                },
                function ($model) {
                    return Auth::user()->can('editByOwner', [ENTITY_PROPOSAL_SNIPPET, $model->user_id]);
                },
            ],
        ];
    }
}
