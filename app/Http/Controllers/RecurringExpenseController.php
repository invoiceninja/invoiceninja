<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRecurringExpenseRequest;
use App\Http\Requests\RecurringExpenseRequest;
use App\Http\Requests\UpdateRecurringExpenseRequest;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Ninja\Datatables\RecurringExpenseDatatable;
use App\Ninja\Repositories\RecurringExpenseRepository;
use App\Services\RecurringExpenseService;
use Auth;
use Input;
use Session;
use View;
use Cache;

class RecurringExpenseController extends BaseController
{
    protected $recurringExpenseRepo;
    protected $recurringExpenseService;
    protected $entityType = ENTITY_RECURRING_EXPENSE;

    public function __construct(RecurringExpenseRepository $recurringExpenseRepo, RecurringExpenseService $recurringExpenseService)
    {
        $this->recurringExpenseRepo = $recurringExpenseRepo;
        $this->recurringExpenseService = $recurringExpenseService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_RECURRING_EXPENSE,
            'datatable' => new RecurringExpenseDatatable(),
            'title' => trans('texts.recurring_expenses'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        $search = Input::get('sSearch');
        $userId = Auth::user()->filterId();

        return $this->recurringExpenseService->getDatatable($search, $userId);
    }

    public function create(RecurringExpenseRequest $request)
    {
        if ($request->vendor_id != 0) {
            $vendor = Vendor::scope($request->vendor_id)->with('vendor_contacts')->firstOrFail();
        } else {
            $vendor = null;
        }

        $data = [
            'vendorPublicId' => Input::old('vendor') ? Input::old('vendor') : $request->vendor_id,
            'expense' => null,
            'method' => 'POST',
            'url' => 'recurring_expenses',
            'title' => trans('texts.new_expense'),
            'vendors' => Vendor::scope()->with('vendor_contacts')->orderBy('name')->get(),
            'vendor' => $vendor,
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $request->client_id,
            'categoryPublicId' => $request->category_id,
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('expenses.edit', $data);
    }

    public function edit(RecurringExpenseRequest $request)
    {
        $expense = $request->entity();

        $actions = [];
        if (! $expense->trashed()) {
            $actions[] = ['url' => 'javascript:submitAction("archive")', 'label' => trans('texts.archive_expense')];
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans('texts.delete_expense')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans('texts.restore_expense')];
        }

        $data = [
            'vendor' => null,
            'expense' => $expense,
            'entity' => $expense,
            'method' => 'PUT',
            'url' => 'recurring_expenses/'.$expense->public_id,
            'title' => 'Edit Expense',
            'actions' => $actions,
            'vendors' => Vendor::scope()->with('vendor_contacts')->orderBy('name')->get(),
            'vendorPublicId' => $expense->vendor ? $expense->vendor->public_id : null,
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $expense->client ? $expense->client->public_id : null,
            'categoryPublicId' => $expense->expense_category ? $expense->expense_category->public_id : null,
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('expenses.edit', $data);
    }

    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'account' => Auth::user()->account,
            'categories' => ExpenseCategory::whereAccountId(Auth::user()->account_id)->withArchived()->orderBy('name')->get(),
            'taxRates' => TaxRate::scope()->whereIsInclusive(false)->orderBy('name')->get(),
            'isRecurring' => true,
        ];
    }

    public function store(CreateRecurringExpenseRequest $request)
    {
        $recurringExpense = $this->recurringExpenseService->save($request->input());

        Session::flash('message', trans('texts.created_recurring_expense'));

        return redirect()->to($recurringExpense->getRoute());
    }

    public function update(UpdateRecurringExpenseRequest $request)
    {
        $recurringExpense = $this->recurringExpenseService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_recurring_expense'));

        if (in_array(Input::get('action'), ['archive', 'delete', 'restore'])) {
            return self::bulk();
        }

        return redirect()->to($recurringExpense->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->recurringExpenseService->bulk($ids, $action);

        if ($count > 0) {
            $field = $count == 1 ? "{$action}d_recurring_expense" : "{$action}d_recurring_expenses";
            $message = trans("texts.$field", ['count' => $count]);
            Session::flash('message', $message);
        }

        return $this->returnBulk($this->entityType, $action, $ids);
    }
}
