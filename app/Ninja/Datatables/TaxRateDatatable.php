<?php

namespace App\Ninja\Datatables;

use URL;

/**
 * Class TaxRateDatatable
 */
class TaxRateDatatable extends EntityDatatable
{
    public $entityType = ENTITY_TAX_RATE;

    /**
     * @return array
     */
    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("tax_rates/{$model->public_id}/edit", $model->name)->toHtml();
                }
            ],
            [
                'rate',
                function ($model) {
                    return $model->rate . '%';
                }
            ]
        ];
    }

    /**
     * @return array
     */
    public function actions()
    {
        return [
            [
                uctrans('texts.edit_tax_rate'),
                function ($model) {
                    return URL::to("tax_rates/{$model->public_id}/edit");
                }
            ]
        ];
    }

}
