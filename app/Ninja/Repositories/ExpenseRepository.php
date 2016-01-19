<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use App\Models\Expense;
use App\Models\Vendor;
use App\Ninja\Repositories\BaseRepository;
use Session;

class ExpenseRepository extends BaseRepository
{
    // Expenses
    public function getClassName()
    {
        return 'App\Models\Expense';
    }

    public function all()
    {
        return Expense::scope()
                ->with('user')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function findVendor($vendorPublicId)
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    ->where('expenses.account_id', '=', $accountid)
                    ->where('expenses.vendor_id','=',$vendorPublicId)
                    ->select('expenses.id',
                             'expenses.expense_date',
                             'expenses.amount',
                             'expenses.public_notes',
                             'expenses.public_id',
                             'expenses.deleted_at','expenses.is_invoiced','expenses.should_be_invoiced','expenses.created_at');
         return $query;
    }

    public function find($filter = null)
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    ->leftjoin('vendors','vendors.public_id','=', 'expenses.vendor_id')
                    ->where('expenses.account_id', '=', $accountid)
                    ->select('expenses.account_id',
                        'expenses.amount',
                        'expenses.amount_cur',
                        'expenses.currency_id',
                        'expenses.deleted_at',
                        'expenses.exchange_rate',
                        'expenses.expense_date',
                        'expenses.id',
                        'expenses.is_deleted',
                        'expenses.is_invoiced',
                        'expenses.private_notes',
                        'expenses.public_id',
                        'expenses.public_notes',
                        'expenses.should_be_invoiced',
                        'expenses.vendor_id',
                        'vendors.name as vendor_name',
                        'vendors.public_id as vendor_public_id');

        $showTrashed = \Session::get('show_trash:expense');

        if (!$showTrashed) {
            $query->where('expenses.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('expenses.public_notes', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    public function save($input)
    {
        $publicId = isset($input['public_id']) ? $input['public_id'] : false;

        if ($publicId) {
            $expense = Expense::scope($publicId)->firstOrFail();
        } else {
            $expense = Expense::createNew();
        }

        // First auto fill
        $expense->fill($input);

        // We can have an expense without a vendor
        if(isset($input['vendor'])) {
            $expense->vendor_id = $input['vendor'];
        }

        $expense->expense_date = Utils::toSqlDate($input['expense_date']);
        $expense->amount = Utils::parseFloat($input['amount']);

        if(isset($input['amount_cur']))
            $expense->amount_cur = Utils::parseFloat($input['amount_cur']);

        $expense->private_notes = trim($input['private_notes']);
        $expense->public_notes = trim($input['public_notes']);

        if(isset($input['exchange_rate']))
            $expense->exchange_rate = Utils::parseFloat($input['exchange_rate']);
        else
            $expense->exchange_rate = 100;

        if($expense->exchange_rate == 0)
            $expense->exchange_rate = 100;

        // set the currency
        if(isset($input['currency_id']))
            $expense->currency_id = $input['currency_id'];

        if($expense->currency_id == 0)
            $expense->currency_id = Session::get(SESSION_CURRENCY, DEFAULT_CURRENCY);

        // Calculate the amount cur
        $expense->amount_cur = ($expense->amount / 100) * $expense->exchange_rate;

        $expense->should_be_invoiced = isset($input['should_be_invoiced']) ? true : false;
        if(isset($input['client'])) {
            $expense->invoice_client_id = $input['client'];
        }
        $expense->save();

        return $expense;
    }

    public function bulk($ids, $action)
    {
        $expenses = Expense::withTrashed()->scope($ids)->get();

        foreach ($expenses as $expense) {
            if ($action == 'restore') {
                $expense->restore();

                $expense->is_deleted = false;
                $expense->save();
            } else {
                if ($action == 'delete') {
                    $expense->is_deleted = true;
                    $expense->save();
                }

                $expense->delete();
            }
        }

        return count($tasks);
    }

}
