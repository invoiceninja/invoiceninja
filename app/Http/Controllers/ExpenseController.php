<?php namespace App\Http\Controllers;

use Auth;
use Datatable;
use Utils;
use View;
use URL;
use Validator;
use Input;
use Session;
use Redirect;
use Cache;
use App\Models\Vendor;
use App\Services\ExpenseService;
use App\Ninja\Repositories\ExpenseRepository;
use App\Http\Requests\CreateExpenseRequest;

class ExpenseController extends BaseController
{
    protected $expenseRepo;
    protected $expenseService;

    public function __construct(ExpenseRepository $expenseRepo, ExpenseService $expenseService)
    {
        parent::__construct();

        $this->expenseRepo = $expenseRepo;
        $this->expenseService = $expenseService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list', array(
            'entityType' => ENTITY_EXPENSE,
            'title' => trans('texts.expenses'),
            'sortCol' => '4',
            'columns' => Utils::trans([
              'checkbox',
              'vendor',
              'expense_amount',
              'expense_balance',
              'expense_date',
              'private_notes',
              ''
            ]),
        ));
    }

    public function getDatatable($vendorPublicId = null)
    {
        return $this->expenseService->getDatatable($vendorPublicId, Input::get('sSearch'));
    }

    public function create($vendorPublicId = 0)
    {
        $vendor = Vendor::scope($vendorPublicId)->with('vendorcontacts')->firstOrFail();
        $data = array(
            'vendorPublicId' => Input::old('vendor') ? Input::old('vendor') : $vendorPublicId,
            'expense' => null,
            'method' => 'POST',
            'url' => 'expenses',
            'title' => trans('texts.new_expense'),
            'vendors' => Vendor::scope()->with('vendorcontacts')->orderBy('name')->get(), 
            );

        $data = array_merge($data, self::getViewModel());

        return View::make('expenses.edit', $data);
    }

    public function edit($publicId)
    {
        $expense = Expense::scope($publicId)->firstOrFail();
        $expense->expense_date = Utils::fromSqlDate($expense->expense_date);

        $data = array(
            'vendor' => null,
            'expense' => $expense,
            'method' => 'PUT',
            'url' => 'expenses/'.$publicId,
            'title' => 'Edit Expense',
            'vendors' => Vendor::scope()->with('vendorcontacts')->orderBy('name')->get(), );

        return View::make('expense.edit', $data);
    }

    public function store(CreateExpenseRequest $request)
    {
        $expense = $this->expenseRepo->save($request->input());
        
        Session::flash('message', trans('texts.created_expense'));
        
        return redirect()->to('expenses');
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->expenseService->bulk($ids, $action);

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_expense', $count);
            Session::flash('message', $message);
        }

        return Redirect::to('expenses');
    }
    
    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'account' => Auth::user()->account,
            'sizes' => Cache::get('sizes'),
            'paymentTerms' => Cache::get('paymentTerms'),
            'industries' => Cache::get('industries'),
            'currencies' => Cache::get('currencies'),
            'languages' => Cache::get('languages'),
            'countries' => Cache::get('countries'),
            'customLabel1' => Auth::user()->account->custom_vendor_label1,
            'customLabel2' => Auth::user()->account->custom_vendor_label2,
        ];
    }
    
}
