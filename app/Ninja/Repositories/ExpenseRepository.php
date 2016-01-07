<?php namespace App\Ninja\Repositories;

use DB;
use Utils;
use App\Models\Expense;
use App\Models\Vendor;
use App\Ninja\Repositories\BaseRepository;

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
    
    public function find($filter = null)
    {
        /*
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    ->join('vendors', 'vendors.id', '=', 'expenses.vendor_id')
                    ->join('vendor_contacts', 'vendor_contacts.vendor_id', '=', 'vendors.id')
                    ->where('vendors.account_id', '=', \Auth::user()->account_id)
                    ->where('vendors.deleted_at', '=', null)
                    ->where('vendor_contacts.deleted_at', '=', null)
                    ->where('vendor_contacts.is_primary', '=', true)
                    ->select(
                        DB::raw('COALESCE(vendors.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(vendors.country_id, accounts.country_id) country_id'),
                        'expenses.public_id',
                        'vendors.name as vendor_name',
                        'vendors.public_id as vendor_public_id',
                        'expenses.amount',
                        'expenses.balance',
                        'expenses.expense_date',
                        'vendor_contacts.first_name',
                        'vendor_contacts.last_name',
                        'vendor_contacts.email',
                        'expenses.private_notes',
                        'expenses.deleted_at',
                        'expenses.is_deleted'
                    );
        */
        $accountid = \Auth::user()->account_id;
        $query = DB::table('expenses')
                    ->join('accounts', 'accounts.id', '=', 'expenses.account_id')
                    //->join('vendors', 'vendors.id', '=', 'expenses.vendor_id')
                    ->where('expenses.account_id', '=', $accountid)
                    ->where('expenses.deleted_at', '=', null)
                    ->select(
                        //DB::raw('COALESCE(vendors.currency_id, accounts.currency_id) currency_id'),
                        //DB::raw('COALESCE(vendors.country_id, accounts.country_id) country_id'),
                        'expenses.public_id',
                        //'vendors.name as vendor_name',
                        //'vendors.public_id as vendor_public_id',
                        'expenses.amount',
                        'expenses.balance',
                        'expenses.expense_date',
                        'expenses.public_notes',
                        'expenses.deleted_at',
                        'expenses.is_deleted'
                    );
        
        if (!\Session::get('show_trash:expense')) {
            $query->where('expenses.deleted_at', '=', null);
        }
/*
        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('vendors.name', 'like', '%'.$filter.'%');
            });
        }
*/
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

        // First auto fille
        $expense->fill($input);
        
        // We can have an expense without a vendor
        if(isset($input['vendor'])) {
            $expense->vendor_id = Vendor::getPrivateId($input['vendor']);    
        }
        
        $expense->expense_date = Utils::toSqlDate($input['expense_date']);
        $expense->amount = Utils::parseFloat($input['amount']);
        
        if(isset($input['amountcur']))
            $expense->amountcur = Utils::parseFloat($input['amountcur']);
        
        $expense->balance = Utils::parseFloat($input['amount']);
        $expense->private_notes = trim($input['private_notes']);
        
        if(isset($input['exchange_rate']))
            $expense->exchange_rate = Utils::parseFloat($input['exchange_rate']);

        $expense->save();

        return $expense;
    }
}
