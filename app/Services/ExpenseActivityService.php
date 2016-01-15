<?php namespace App\Services;

use Utils;
use App\Models\Expense;
use App\Services\BaseService;
use App\Ninja\Repositories\ExpenseActivityRepository;

class ExpenseActivityService extends BaseService
{
    protected $activityRepo;
    protected $datatableService;

    public function __construct(ExpenseActivityRepository $activityRepo, DatatableService $datatableService)
    {
        $this->activityRepo = $activityRepo;
        $this->datatableService = $datatableService;
    }

    public function getDatatable($expensePublicId = null)
    {
        $expenseId = Expense::getPrivateId($expensePublicId);

        $query = $this->activityRepo->findByExpenseId($expenseId);

        return $this->createDatatable(ENTITY_EXPENSE_ACTIVITY, $query);
    }

    protected function getDatatableColumns($entityType, $hideExpense)
    {
        return [
            [
                'expense_activities.id',
                function ($model) {
                    return Utils::timestampToDateTimeString(strtotime($model->created_at));
                }
            ],
            [
                'activity_type_id',
                function ($model) {
                    $data = [
                        'expense' => link_to('/expenses/' . $model->public_id, trans('texts.view_expense',['expense' => $model->public_id])),
                        'user' => $model->is_system ? '<i>' . trans('texts.system') . '</i>' : Utils::getPersonDisplayName($model->user_first_name, $model->user_last_name, $model->user_email), 
                    ];

                    return trans("texts.activity_{$model->activity_type_id}", $data);
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount);
                }
            ],
            [
                'expense_date',
                function ($model) {
                    return Utils::fromSqlDate($model->expense_date);
                }
            ]
        ];
    }
}