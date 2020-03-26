<?php

namespace App\Ninja\Repositories;

use App\Models\RecurringExpense;
use App\Models\Expense;
use App\Models\Vendor;
use Auth;
use DB;
use Utils;

class RecurringExpenseRepository extends BaseRepository
{
    // Expenses
    public function getClassName()
    {
        return 'App\Models\RecurringExpense';
    }

    public function all()
    {
        return RecurringExpense::scope()
                ->with('user')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function find($filter = null)
    {
        $accountid = \Auth::user()->account_id;
        $query = DB::table('recurring_expenses')
                    ->join('accounts', 'accounts.id', '=', 'recurring_expenses.account_id')
                    ->leftjoin('clients', 'clients.id', '=', 'recurring_expenses.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftjoin('vendors', 'vendors.id', '=', 'recurring_expenses.vendor_id')
                    ->join('frequencies', 'frequencies.id', '=', 'recurring_expenses.frequency_id')
                    ->leftJoin('expense_categories', 'recurring_expenses.expense_category_id', '=', 'expense_categories.id')
                    ->where('recurring_expenses.account_id', '=', $accountid)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('vendors.deleted_at', '=', null)
                    ->where('clients.deleted_at', '=', null)
                    ->where(function ($query) { // handle when client isn't set
                        $query->where('contacts.is_primary', '=', true)
                              ->orWhere('contacts.is_primary', '=', null);
                    })
                    ->select(
                        'recurring_expenses.account_id',
                        'recurring_expenses.amount',
                        'recurring_expenses.deleted_at',
                        'recurring_expenses.id',
                        'recurring_expenses.is_deleted',
                        'recurring_expenses.private_notes',
                        'recurring_expenses.public_id',
                        'recurring_expenses.public_notes',
                        'recurring_expenses.should_be_invoiced',
                        'recurring_expenses.vendor_id',
                        'recurring_expenses.expense_currency_id',
                        'recurring_expenses.invoice_currency_id',
                        'recurring_expenses.user_id',
                        'recurring_expenses.tax_rate1',
                        'recurring_expenses.tax_rate2',
                        'recurring_expenses.private_notes',
                        'frequencies.name as frequency',
                        'expense_categories.name as category',
                        'expense_categories.user_id as category_user_id',
                        'expense_categories.public_id as category_public_id',
                        'vendors.name as vendor_name',
                        'vendors.public_id as vendor_public_id',
                        'vendors.user_id as vendor_user_id',
                        DB::raw("COALESCE(NULLIF(clients.name,''), NULLIF(CONCAT(contacts.first_name, ' ', contacts.last_name),''), NULLIF(contacts.email,'')) client_name"),
                        'clients.public_id as client_public_id',
                        'clients.user_id as client_user_id',
                        'contacts.first_name',
                        'contacts.email',
                        'contacts.last_name',
                        'clients.country_id as client_country_id'
                    );

        $this->applyFilters($query, ENTITY_RECURRING_EXPENSE);

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('recurring_expenses.public_notes', 'like', '%'.$filter.'%')
                      ->orWhere('clients.name', 'like', '%'.$filter.'%')
                      ->orWhere('vendors.name', 'like', '%'.$filter.'%')
                      ->orWhere('expense_categories.name', 'like', '%'.$filter.'%');
                ;
            });
        }

        return $query;
    }

    public function save($input, $expense = null)
    {
        $publicId = isset($input['public_id']) ? $input['public_id'] : false;

        if ($expense) {
            // do nothing
        } elseif ($publicId) {
            $expense = RecurringExpense::scope($publicId)->firstOrFail();
            if (Utils::isNinjaDev()) {
                \Log::warning('Entity not set in expense repo save');
            }
        } else {
            $expense = RecurringExpense::createNew();
        }

        if ($expense->is_deleted) {
            return $expense;
        }

        // First auto fill
        $expense->fill($input);

        if (isset($input['start_date'])) {
            if ($expense->exists && !$expense->deleted_at && $expense->start_date && $expense->start_date != Utils::toSqlDate($input['start_date'])) {
                $expense->last_sent_date = null;
            }
            $expense->start_date = Utils::toSqlDate($input['start_date']);
        }
        if (isset($input['end_date'])) {
            $expense->end_date = Utils::toSqlDate($input['end_date']);
        }

        if (! $expense->expense_currency_id) {
            $expense->expense_currency_id = \Auth::user()->account->getCurrencyId();
        }

        /*
        if (! $expense->invoice_currency_id) {
            $expense->invoice_currency_id = \Auth::user()->account->getCurrencyId();
        }
        $rate = isset($input['exchange_rate']) ? Utils::parseFloat($input['exchange_rate']) : 1;
        $expense->exchange_rate = round($rate, 4);
        if (isset($input['amount'])) {
            $expense->amount = round(Utils::parseFloat($input['amount']), 2);
        }
        */

        $expense->save();

        return $expense;
    }

    public function createRecurringExpense(RecurringExpense $recurringExpense)
    {
        if ($recurringExpense->client && $recurringExpense->client->deleted_at) {
            return false;
        }

        if (! $recurringExpense->user->confirmed) {
            return false;
        }

        if (! $recurringExpense->shouldSendToday()) {
            return false;
        }

        $account = $recurringExpense->account;
        $expense = Expense::createNew($recurringExpense);

        $fields = [
            'vendor_id',
            'client_id',
            'amount',
            'public_notes',
            'private_notes',
            'invoice_currency_id',
            'expense_currency_id',
            'should_be_invoiced',
            'expense_category_id',
            'tax_name1',
            'tax_rate1',
            'tax_name2',
            'tax_rate2',
        ];

        foreach ($fields as $field) {
            $expense->$field = $recurringExpense->$field;
        }

        $expense->expense_date = $account->getDateTime()->format('Y-m-d');
        $expense->exchange_rate = 1;
        $expense->invoice_currency_id = $recurringExpense->expense_currency_id;
        $expense->recurring_expense_id = $recurringExpense->id;
        $expense->save();

        $recurringExpense->last_sent_date = $account->getDateTime()->format('Y-m-d');
        $recurringExpense->save();

        return $expense;
    }
}
