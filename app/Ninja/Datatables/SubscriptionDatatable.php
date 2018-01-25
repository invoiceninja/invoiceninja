<?php

namespace App\Ninja\Datatables;

use URL;

class SubscriptionDatatable extends EntityDatatable
{
    public $entityType = ENTITY_SUBSCRIPTION;

    public function columns()
    {
        return [
            [
                'event',
                function ($model) {
                    return trans('texts.subscription_event_' . $model->event);
                },
            ],
            [
                'target',
                function ($model) {
                    return $this->showWithTooltip($model->target, 40);
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                uctrans('texts.edit_subscription'),
                function ($model) {
                    return URL::to("subscriptions/{$model->public_id}/edit");
                },
            ],
        ];
    }
}
