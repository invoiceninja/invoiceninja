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
                    if (! Auth::user()->can('viewByOwner', [ENTITY_QUOTE, $model->invoice_user_id])) {
                        return $model->invoice_number;
                    }

                    return link_to("quotes/{$model->invoice_public_id}", $model->invoice_number)->toHtml();
                },
            ],
            [
                'client',
                function ($model) {
                    if (! Auth::user()->can('viewByOwner', [ENTITY_CLIENT, $model->client_user_id])) {
                        return $model->client;
                    }

                    return link_to("clients/{$model->client_public_id}", $model->client)->toHtml();
                },
            ],
            [
                'template',
                function ($model) {
                    if (! Auth::user()->can('viewByOwner', [ENTITY_PROPOSAL_TEMPLATE, $model->template_user_id])) {
                        return $model->template ?: ' ';
                    }

                    return link_to("proposals/templates/{$model->template_public_id}/edit", $model->template ?: ' ')->toHtml();
                },
            ],
            [
                'created_at',
                function ($model) {
                    if (! Auth::user()->can('viewByOwner', [ENTITY_PROPOSAL, $model->user_id])) {
                        return Utils::timestampToDateString(strtotime($model->created_at));
                    }

                    return link_to("proposals/{$model->public_id}/edit", Utils::timestampToDateString(strtotime($model->created_at)))->toHtml();
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
