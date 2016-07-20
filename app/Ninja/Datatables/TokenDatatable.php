<?php

namespace App\Ninja\Datatables;

use URL;

/**
 * Class TokenDatatable
 */
class TokenDatatable extends EntityDatatable
{
    public $entityType = ENTITY_TOKEN;

    /**
     * @return array
     */
    public function columns()
    {
        return [
            [
                'name',
                function ($model) {
                    return link_to("tokens/{$model->public_id}/edit", $model->name)->toHtml();
                }
            ],
            [
                'token',
                function ($model) {
                    return $model->token;
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
                uctrans('texts.edit_token'),
                function ($model) {
                    return URL::to("tokens/{$model->public_id}/edit");
                }
            ]
        ];
    }

}
