<?php

namespace App\Ninja\Presenters;

use Utils;
use DropdownButton;

/**
 * Class ProposalPresenter.
 */
class ProposalPresenter extends EntityPresenter
{
    public function moreActions()
    {
        $proposal = $this->entity;
        $invitation = $proposal->invitations->first();
        $actions = [];

        $actions[] = ['url' => $invitation->getLink('proposal'), 'label' => trans("texts.view_in_portal")];

        $actions[] = DropdownButton::DIVIDER;

        if (! $proposal->trashed()) {
            $actions[] = ['url' => 'javascript:onArchiveClick()', 'label' => trans("texts.archive_proposal")];
        }
        if (! $proposal->is_deleted) {
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans("texts.delete_proposal")];
        }

        return $actions;
    }

    public function htmlDocument()
    {
        $proposal = $this->entity;

        $html = "<html>
                    <head>
                        <style>
                            @page {
                                margin: 0px;
                            }
                            {$proposal->css}
                        </style>
                    </head>
                    <body>
                        {$proposal->html}
                    </body>
                </html>";

        return $html;
    }

    public function filename()
    {
        $proposal = $this->entity;

        return sprintf('%s_%s.pdf', trans('texts.proposal'), $proposal->invoice->invoice_number);
    }
}
