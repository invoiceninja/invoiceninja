<?php

namespace App\Ninja\Datatables;

use URL;

/**
 * Class BankAccountDatatable
 */
class BankAccountDatatable extends EntityDatatable
{
    public $entityType = ENTITY_BANK_ACCOUNT;

    /**
     * @return array
     */
    public function columns()
    {
        return [
            [
                'bank_name',
                function ($model) {
                    return link_to("bank_accounts/{$model->public_id}/edit", $model->bank_name)->toHtml();
                },
            ],
            [
                'bank_library_id',
                function () {
                    return 'OFX';
                }
            ],
        ];
    }

    /**
     * @return array
     */
    public function actions()
    {
        return [
            [
                uctrans('texts.edit_bank_account'),
                function ($model) {
                    return URL::to("bank_accounts/{$model->public_id}/edit");
                },
            ]
        ];
    }
}
