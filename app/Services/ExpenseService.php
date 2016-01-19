<?php namespace App\Services;

use DB;
use Utils;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\ExpenseRepository;


class ExpenseService extends BaseService
{
       // Expenses
    protected $expenseRepo;
    protected $datatableService;

    public function __construct(ExpenseRepository $expenseRepo, DatatableService $datatableService)
    {
        $this->expenseRepo = $expenseRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->expenseRepo;
    }

    public function save($data)
    {
        return $this->expenseRepo->save($data);
    }

    public function getDatatable($search)
    {
        $query = $this->expenseRepo->find($search);

        return $this->createDatatable(ENTITY_EXPENSE, $query);
    }

    public function getDatatableVendor($vendorPublicId)
    {
        $query = $this->expenseRepo->findVendor($vendorPublicId);
        return $this->datatableService->createDatatable(ENTITY_EXPENSE,
                                                        $query,
                                                        $this->getDatatableColumnsVendor(ENTITY_EXPENSE,false),
                                                        $this->getDatatableActionsVendor(ENTITY_EXPENSE),
                                                        false);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'vendor_name',
                function ($model)
                {
                    if($model->vendor_public_id) {
                        return link_to("vendors/{$model->vendor_public_id}", $model->vendor_name);
                    } else {
                        return 'No vendor' ;
                    }
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, false, false);
                }
            ],
            [
                'expense_date',
                function ($model) {
                    return Utils::fromSqlDate($model->expense_date);
                }
            ],
            [
                'public_notes',
                function ($model) {
                    return $model->public_notes != null ? $model->public_notes : '';
                }
            ],
            [
                'is_invoiced',
                function ($model) {
                    return $model->is_invoiced ? trans('texts.yes') : trans('texts.no');
                }
            ],
            [
                'should_be_invoiced',
                function ($model) {
                    return $model->should_be_invoiced ? trans('texts.yes') : trans('texts.no');
                }
            ],
        ];
    }

    protected function getDatatableColumnsVendor($entityType, $hideClient)
    {
        return [
            /*
                [
                'expenses.id',
                function ($model) {
                    return Utils::timestampToDateTimeString(strtotime($model->created_at));
                }
            ],*/
            [
                'expense_date',
                function ($model) {
                    return $model->expense_date;
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, false, false);
                }
            ],
            [
                'public_notes',
                function ($model) {
                    return $model->public_notes != null ? $model->public_notes : '';
                }
            ],
            [
                'is_invoiced',
                function ($model) {
                    return $model->is_invoiced ? trans('texts.yes') : trans('texts.no');
                }
            ],
            [
                'should_be_invoiced',
                function ($model) {
                    return $model->should_be_invoiced ? trans('texts.yes') : trans('texts.no');
                }
            ],
        ];
    }

    protected function getDatatableActions($entityType)
    {
            return [
            [
                trans('texts.invoice_expense'),
                function ($model) {
                    return URL::to("expense/invoice/{$model->public_id}") . '?client=1';
                }
            ],
            [
                trans('texts.view'),
                function ($model) {
                    return URL::to("expenses/{$model->public_id}") ;
                }
            ],
            [
                trans('texts.edit'),
                function ($model) {
                    return URL::to("expenses/{$model->public_id}/edit") ;
                }
            ],

        ];
    }
    protected function getDatatableActionsVendor($entityType)
    {
            return [
            [
                trans('texts.invoice_expense'),
                function ($model) {
                    return URL::to("expense/invoice/{$model->public_id}") . '?client=1';
                }
            ],
            [
                trans('texts.view'),
                function ($model) {
                    return URL::to("expenses/{$model->public_id}") ;
                }
            ],
            [
                trans('texts.edit'),
                function ($model) {
                    return URL::to("expenses/{$model->public_id}/edit") ;
                }
            ],

        ];
    }

}
