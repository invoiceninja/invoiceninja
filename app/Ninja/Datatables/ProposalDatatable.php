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
                    if (Auth::user()->can('view', [ENTITY_QUOTE, $model]))
                        return link_to("quotes/{$model->invoice_public_id}", $model->invoice_number)->toHtml();
                    else
                        return $model->invoice_number;

                },
            ],
            [
                'client',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_CLIENT, $model]))
                        return link_to("clients/{$model->client_public_id}", $model->client)->toHtml();
                    else
                        return $model->client;
                },
            ],
            [
                'template',
                function ($model) {
                    if(Auth::user()->can('view', [ENTITY_PROPOSAL_TEMPLATE, $model]))
                        return link_to("proposals/templates/{$model->template_public_id}/edit", $model->template ?: ' ')->toHtml();
                    else
                        return $model->template ?: ' ';

                },
            ],
            [
                'created_at',
                function ($model) {
                    if (Auth::user()->can('view', [ENTITY_PROPOSAL, $model]))
                        return link_to("proposals/{$model->public_id}/edit", Utils::timestampToDateString(strtotime($model->created_at)))->toHtml();
                    else
                        return Utils::timestampToDateString(strtotime($model->created_at));

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
                    return Auth::user()->can('view', [ENTITY_PROPOSAL, $model]) ;
                },
            ],
        ];
    }
}
