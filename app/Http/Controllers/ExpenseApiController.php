<?php namespace App\Http\Controllers;

use Debugbar;
use DB;
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
use App\Models\Expense;
use App\Models\Client;
use App\Services\ExpenseService;
use App\Ninja\Repositories\ExpenseRepository;
use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;

class ExpenseApiController extends BaseController
{
    // Expenses
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
            'sortCol' => '1',
            'columns' => Utils::trans([
              'checkbox',
              'vendor',
              'expense_amount',
              'expense_date',
              'public_notes',
              'is_invoiced',
              'should_be_invoiced',
              ''
            ]),
        ));
    }

    public function getDatatable($expensePublicId = null)
    {
        return $this->expenseService->getDatatable($expensePublicId, Input::get('sSearch'));
    }

    public function getDatatableVendor($vendorPublicId = null)
    {
        return $this->expenseService->getDatatableVendor($vendorPublicId);
    }

    public function create($vendorPublicId = 0)
    {
        if($vendorPublicId != 0) {
            $vendor = Vendor::scope($vendorPublicId)->with('vendorcontacts')->firstOrFail();
        } else {
            $vendor = null;
        }
        $data = array(
            'vendorPublicId' => Input::old('vendor') ? Input::old('vendor') : $vendorPublicId,
            'expense' => null,
            'method' => 'POST',
            'url' => 'expenses',
            'title' => trans('texts.new_expense'),
            'vendors' => Vendor::scope()->with('vendorcontacts')->orderBy('name')->get(),
            'vendor' => $vendor,
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => null,
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
            'vendors' => Vendor::scope()->with('vendorcontacts')->orderBy('name')->get(),
            'vendorPublicId' => $expense->vendor_id,
            'clients' => Client::scope()->with('contacts')->orderBy('name')->get(),
            'clientPublicId' => $expense->invoice_client_id,
            );

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->account->isNinjaAccount()) {
            if ($account = Account::whereId($client->public_id)->first()) {
                $data['proPlanPaid'] = $account['pro_plan_paid'];
            }
        }

        return View::make('expenses.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateExpenseRequest $request)
    {
        $client = $this->expenseRepo->save($request->input());

        Session::flash('message', trans('texts.updated_expense'));

        return redirect()->to('expenses');
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
        $ids    = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        switch($action)
        {
            case 'invoice':
                $expenses       = Expense::scope($ids)->get();
                $clientPublicId = null;
                $data           = [];

                // Validate that either all expenses do not have a client or if there is a client, it is the same client
                foreach ($expenses as $expense)
                {
                    if ($expense->client_id) {
                        if (!$clientPublicId) {
                            $clientPublicId = $expense->client_id;
                    } else if ($clientPublicId != $expense->client_id) {
                        Session::flash('error', trans('texts.expense_error_multiple_clients'));
                        return Redirect::to('expenses');
                    }
                    }

                    if ($expense->invoice_id) {
                        Session::flash('error', trans('texts.expense_error_invoiced'));
                        return Redirect::to('expenses');
                    }

                    if ($expense->should_be_invoiced == 0) {
                        Session::flash('error', trans('texts.expense_error_should_not_be_invoiced'));
                        return Redirect::to('expenses');
                    }

                    $account = Auth::user()->account;
                    $data[] = [
                        'publicId' => $expense->public_id,
                        'description' => $expense->public_notes,
                        'qty' => 1,
                        'cost' => $expense->amount,
                    ];
                }

                return Redirect::to("invoices/create/{$clientPublicId}")->with('expenses', $data);
                break;

            default:
                $count  = $this->expenseService->bulk($ids, $action);
        }

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

    public function show($publicId)
    {
        $expense = Expense::withTrashed()->scope($publicId)->firstOrFail();

        if($expense) {
            Utils::trackViewed($expense->getDisplayName(), 'expense');
        }

        $actionLinks = [
            ['label' => trans('texts.new_expense'), 'url' => '/expenses/create/']
        ];

        $data = array(
            'actionLinks' => $actionLinks,
            'showBreadcrumbs' => false,
            'expense' => $expense,
            'credit' =>0,
            'vendor' => $expense->vendor,
            'title' => trans('texts.view_expense',['expense' => $expense->public_id]),
        );

        return View::make('expenses.show', $data);
    }
}
