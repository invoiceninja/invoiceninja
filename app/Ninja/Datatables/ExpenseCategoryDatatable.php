<?php namespace App\Ninja\Datatables;

use Utils;
use URL;
use Auth;

class ExpenseCategoryDatatable extends EntityDatatable
{
    public $entityType = ENTITY_EXPENSE_CATEGORY;

    public function columns()
    {
        return [
            [
                'name',
                function ($model)
                {
                    return link_to("expense_categories/{$model->public_id}/edit", $model->category ?: '')->toHtml();
                }
            ],
        ];
    }

    public function actions()
    {
        return [
            [
                trans('texts.edit_category'),
                function ($model) {
                    return URL::to("expense_categories/{$model->public_id}/edit") ;
                },
                function ($model) {
                    return Auth::user()->is_admin;
                }
            ],
        ];
    }

}
