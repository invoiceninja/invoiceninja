<?php

namespace App\Ninja\Datatables;

use URL;

class TaxRateDatatable extends EntityDatatable
{
    public $entityType = ENTITY_TAX_RATE;

    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("tax_rates/{$model->public_id}/edit", $model->name)->toHtml();
                },
            ],
            [
                'rate',
                function ($model) {
                    return $model->rate . '%';
                },
            ],
            [
                'type',
                function ($model) {
                    return $model->is_inclusive ? trans('texts.inclusive') : trans('texts.exclusive');
                },
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                uctrans('texts.edit_tax_rate'),
                function ($model) {
                    return URL::to("tax_rates/{$model->public_id}/edit");
                },
            ],
        ];
    }
}
