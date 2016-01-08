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

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'vendor_id',
                function ($model)
                {
                    if($model->vendor_id) {
                     
                        $vendors = DB::table('vendors')->where('public_id', '=',$model->vendor_id)->select('id', 'public_id','name')->get();
                        // should only be one!
                        $vendor = $vendors[0];

                        if($vendor) {
                            return link_to("vendors/{$vendor->public_id}", $vendor->name);
                        }
                        return 'no vendor: ' . $model->vendor_id;
                    } else {
                        return 'No vendor:' ;
                    }
                },
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
                    return $model->is_invoiced ? trans('texts.expense_is_invoiced') : trans('texts.expense_is_not_invoiced');
                }
            ],
            [
                'should_be_invoiced',
                function ($model) {
                    return $model->should_be_invoiced ? trans('texts.yes') : trans('texts.no');
                }
            ],
            [
                'public_id',
                function($model) {
                   return link_to("expenses/{$model->public_id}", trans('texts.view_expense', ['expense' => $model->public_id]));
                }
             ]
        ];
    }
/*
    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.apply_expense'),
                function ($model) {
                    return URL::to("espense/create/{$model->vendor_public_id}") . '?paymentTypeId=1';
                }
            ]
        ];
    }
    */
}