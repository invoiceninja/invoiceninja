<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\ExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Client;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Ninja\Datatables\ExpenseDatatable;
use App\Ninja\Repositories\ExpenseRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Services\ExpenseService;
use Auth;
use Cache;
use Input;
use Redirect;
use Request;
use Session;
use URL;
use Utils;
use View;

class ExpenseController extends BaseController
{
    // Expenses
    protected $expenseRepo;
    protected $expenseService;
    protected $entityType = ENTITY_EXPENSE;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    public function __construct(ExpenseRepository $expenseRepo, ExpenseService $expenseService, InvoiceRepository $invoiceRepo)
    {
        // parent::__construct();

        $this->expenseRepo = $expenseRepo;
        $this->expenseService = $expenseService;
        $this->invoiceRepo = $invoiceRepo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list_wrapper', [
            'entityType' => ENTITY_EXPENSE,
            'datatable' => new ExpenseDatatable(),
            'title' => trans('texts.expenses'),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        return $this->expenseService->getDatatable(Input::get('sSearch'));
    }

    public function getDatatableVendor($vendorPublicId = null)
    {
        return $this->expenseService->getDatatableVendor($vendorPublicId);
    }

    public function getDatatableClient($clientPublicId = null)
    {
        return $this->expenseService->getDatatableClient($clientPublicId);
    }

    public function create(ExpenseRequest $request)
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
            'url' => 'expenses',
            'title' => trans('texts.new_expense'),
            'vendor' => $vendor,
            'clientPublicId' => $request->client_id,
            'categoryPublicId' => $request->category_id,
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('expenses.edit', $data);
    }

    public function cloneExpense(ExpenseRequest $request, $publicId)
    {
        return self::edit($request, $publicId, true);
    }

    public function edit(ExpenseRequest $request, $publicId = false, $clone = false)
    {
        $expense = $request->entity();

        $actions = [];

        if (! $clone) {
            $actions[] = ['url' => 'javascript:submitAction("clone")', 'label' => trans("texts.clone_expense")];
        }
        if ($expense->invoice) {
            $actions[] = ['url' => URL::to("invoices/{$expense->invoice->public_id}/edit"), 'label' => trans('texts.view_invoice')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("invoice")', 'label' => trans('texts.invoice_expense')];

            // check for any open invoices
            $invoices = $expense->client_id ? $this->invoiceRepo->findOpenInvoices($expense->client_id) : [];

            foreach ($invoices as $invoice) {
                $actions[] = ['url' => 'javascript:submitAction("add_to_invoice", '.$invoice->public_id.')', 'label' => trans('texts.add_to_invoice', ['invoice' => $invoice->invoice_number])];
            }
        }

        if ($expense->recurring_expense_id) {
            $actions[] = ['url' => URL::to("recurring_expenses/{$expense->recurring_expense->public_id}/edit"), 'label' => trans('texts.view_recurring_expense')];
        }

        $actions[] = \DropdownButton::DIVIDER;
        if (! $expense->trashed()) {
            $actions[] = ['url' => 'javascript:submitAction("archive")', 'label' => trans('texts.archive_expense')];
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans('texts.delete_expense')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans('texts.restore_expense')];
        }

        if ($clone) {
            $expense->id = null;
            $expense->public_id = null;
            $expense->expense_date = date_create()->format('Y-m-d');
            $expense->deleted_at = null;
            $expense->invoice_id = null;
            $expense->payment_date = null;
            $expense->payment_type_id = null;
            $expense->transaction_reference = null;
            while ($expense->documents->count()) {
                $expense->documents->pop();
            }
            $method = 'POST';
            $url = 'expenses';
        } else {
            $method = 'PUT';
            $url = 'expenses/' . $expense->public_id;
        }

        $data = [
            'vendor' => null,
            'expense' => $expense,
            'entity' => $expense,
            'method' => $method,
            'url' => $url,
            'title' => 'Edit Expense',
            'actions' => $actions,
            'vendorPublicId' => $expense->vendor ? $expense->vendor->public_id : null,
            'clientPublicId' => $expense->client ? $expense->client->public_id : null,
            'categoryPublicId' => $expense->expense_category ? $expense->expense_category->public_id : null,
        ];

        $data = array_merge($data, self::getViewModel($expense));

        return View::make('expenses.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function update(UpdateExpenseRequest $request)
    {
        $data = $request->input();
        $data['documents'] = $request->file('documents');

        $expense = $this->expenseService->save($data, $request->entity());

        Session::flash('message', trans('texts.updated_expense'));

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore', 'invoice', 'add_to_invoice'])) {
            return self::bulk();
        }

        if ($action == 'clone') {
            return redirect()->to(sprintf('expenses/%s/clone', $expense->public_id));
        } else {
            return redirect()->to("expenses/{$expense->public_id}/edit");
        }
    }

    public function store(CreateExpenseRequest $request)
    {
        $data = $request->input();
        $data['documents'] = $request->file('documents');

        // check for possible duplicate expense
        $duplcate = Expense::scope()
                    ->whereAmount($request->amount)
                    ->whereExpenseDate(Utils::toSqlDate($request->expense_date))
                    ->orderBy('created_at')
                    ->first();
        if ($duplcate) {
            Session::flash('warning', trans('texts.duplicate_expense_warning',
                ['link' => link_to($duplcate->present()->url, trans('texts.expense_link'), ['target' => '_blank'])]));
        }

        $expense = $this->expenseService->save($data);

        Session::flash('message', trans('texts.created_expense'));

        return redirect()->to("expenses/{$expense->public_id}/edit");
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $referer = Request::server('HTTP_REFERER');

        switch ($action) {
            case 'invoice':
            case 'add_to_invoice':
                $expenses = Expense::scope($ids)->with('client')->get();
                $clientPublicId = null;
                $currencyId = null;

                // Validate that either all expenses do not have a client or if there is a client, it is the same client
                foreach ($expenses as $expense) {
                    if ($expense->client) {
                        if ($expense->client->trashed()) {
                            return redirect($referer)->withError(trans('texts.client_must_be_active'));
                        }

                        if (! $clientPublicId) {
                            $clientPublicId = $expense->client->public_id;
                        } elseif ($clientPublicId != $expense->client->public_id) {
                            return redirect($referer)->withError(trans('texts.expense_error_multiple_clients'));
                        }
                    }

                    if (! $currencyId) {
                        $currencyId = $expense->invoice_currency_id;
                    } elseif ($currencyId != $expense->invoice_currency_id && $expense->invoice_currency_id) {
                        return redirect($referer)->withError(trans('texts.expense_error_multiple_currencies'));
                    }

                    if ($expense->invoice_id) {
                        return redirect($referer)->withError(trans('texts.expense_error_invoiced'));
                    }
                }

                if ($action == 'invoice') {
                    return Redirect::to("invoices/create/{$clientPublicId}")
                            ->with('expenseCurrencyId', $currencyId)
                            ->with('expenses', $ids);
                } else {
                    $invoiceId = Input::get('invoice_id');

                    return Redirect::to("invoices/{$invoiceId}/edit")
                            ->with('expenseCurrencyId', $currencyId)
                            ->with('expenses', $ids);
                }
                break;

            default:
                $count = $this->expenseService->bulk($ids, $action);
        }

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_expense', $count);
            Session::flash('message', $message);
        }

        return $this->returnBulk($this->entityType, $action, $ids);
    }

    private static function getViewModel($expense = false)
    {
        return [
            'data' => Input::old('data'),
            'account' => Auth::user()->account,
            'vendors' => Vendor::scope()->withActiveOrSelected($expense ? $expense->vendor_id : false)->with('vendor_contacts')->orderBy('name')->get(),
            'clients' => Client::scope()->withActiveOrSelected($expense ? $expense->client_id : false)->with('contacts')->orderBy('name')->get(),
            'categories' => ExpenseCategory::whereAccountId(Auth::user()->account_id)->withActiveOrSelected($expense ? $expense->expense_category_id : false)->orderBy('name')->get(),
            'taxRates' => TaxRate::scope()->whereIsInclusive(false)->orderBy('name')->get(),
            'isRecurring' => false,
        ];
    }

    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("expenses/{$publicId}/edit");
    }
}
