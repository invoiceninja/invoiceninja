<?php namespace App\Services;

use Auth;
use DB;
use Utils;
use URL;
use App\Services\BaseService;
use App\Ninja\Repositories\ExpenseRepository;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Vendor;
use App\Ninja\Datatables\ExpenseDatatable;

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

    public function save($data, $expense = null)
    {
        if (isset($data['client_id']) && $data['client_id']) {
            $data['client_id'] = Client::getPrivateId($data['client_id']);
        }

        if (isset($data['vendor_id']) && $data['vendor_id']) {
            $data['vendor_id'] = Vendor::getPrivateId($data['vendor_id']);
        }

        return $this->expenseRepo->save($data, $expense);
    }

    public function getDatatable($search)
    {
        $query = $this->expenseRepo->find($search);

        if(!Utils::hasPermission('view_all')){
            $query->where('expenses.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable(new ExpenseDatatable(), $query);
    }

    public function getDatatableVendor($vendorPublicId)
    {
        $datatable = new ExpenseDatatable(false, true);

        $query = $this->expenseRepo->findVendor($vendorPublicId);

        if(!Utils::hasPermission('view_all')){
            $query->where('expenses.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }


    protected function getDatatableColumnsVendor($entityType, $hideClient)
    {
        return [
            [
                'expense_date',
                function ($model) {
                    return Utils::dateToString($model->expense_date);
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
                'invoice_id',
                function ($model) {
                    return '';
                }
            ],
        ];
    }

    protected function getDatatableActionsVendor($entityType)
    {
        return [];
    }

}
