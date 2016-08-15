<?php namespace App\Ninja\Repositories;

use DB;
use App\Models\Activity;

class DashboardRepository
{
    public function totals($accountId, $userId, $viewAll)
    {
        // total_income, billed_clients, invoice_sent and active_clients
        $select = DB::raw(
            'COUNT(DISTINCT CASE WHEN '.DB::getQueryGrammar()->wrap('invoices.id', true).' IS NOT NULL THEN '.DB::getQueryGrammar()->wrap('clients.id', true).' ELSE null END) billed_clients,
            SUM(CASE WHEN '.DB::getQueryGrammar()->wrap('invoices.invoice_status_id', true).' >= '.INVOICE_STATUS_SENT.' THEN 1 ELSE 0 END) invoices_sent,
            COUNT(DISTINCT '.DB::getQueryGrammar()->wrap('clients.id', true).') active_clients'
        );

        $metrics = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->leftJoin('invoices', 'clients.id', '=', 'invoices.client_id')
            ->where('accounts.id', '=', $accountId)
            ->where('clients.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.is_recurring', '=', false)
            ->where('invoices.invoice_type_id', '=', INVOICE_TYPE_STANDARD);

        if (!$viewAll){
            $metrics = $metrics->where(function($query) use($userId){
                $query->where('invoices.user_id', '=', $userId);
                $query->orwhere(function($query) use($userId){
                    $query->where('invoices.user_id', '=', null);
                    $query->where('clients.user_id', '=', $userId);
                });
            });
        }

        return $metrics->groupBy('accounts.id')->first();
    }

    public function paidToDate($accountId, $userId, $viewAll)
    {
        $select = DB::raw(
            'SUM('.DB::getQueryGrammar()->wrap('clients.paid_to_date', true).') as value,'
                  .DB::getQueryGrammar()->wrap('clients.currency_id', true).' as currency_id'
        );
        $paidToDate = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->where('accounts.id', '=', $accountId)
            ->where('clients.is_deleted', '=', false);

        if (!$viewAll){
            $paidToDate = $paidToDate->where('clients.user_id', '=', $userId);
        }

        return $paidToDate->groupBy('accounts.id')
            ->groupBy(DB::raw('CASE WHEN '.DB::getQueryGrammar()->wrap('clients.currency_id', true).' IS NULL THEN CASE WHEN '.DB::getQueryGrammar()->wrap('accounts.currency_id', true).' IS NULL THEN 1 ELSE '.DB::getQueryGrammar()->wrap('accounts.currency_id', true).' END ELSE '.DB::getQueryGrammar()->wrap('clients.currency_id', true).' END'))
            ->get();
    }

    public function averages($accountId, $userId, $viewAll)
    {
        $select = DB::raw(
            'AVG('.DB::getQueryGrammar()->wrap('invoices.amount', true).') as invoice_avg, '
                  .DB::getQueryGrammar()->wrap('clients.currency_id', true).' as currency_id'
        );
        $averageInvoice = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->leftJoin('invoices', 'clients.id', '=', 'invoices.client_id')
            ->where('accounts.id', '=', $accountId)
            ->where('clients.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.invoice_type_id', '=', INVOICE_TYPE_STANDARD)
            ->where('invoices.is_recurring', '=', false);

        if (!$viewAll){
            $averageInvoice = $averageInvoice->where('invoices.user_id', '=', $userId);
        }

        return $averageInvoice->groupBy('accounts.id')
            ->groupBy(DB::raw('CASE WHEN '.DB::getQueryGrammar()->wrap('clients.currency_id', true).' IS NULL THEN CASE WHEN '.DB::getQueryGrammar()->wrap('accounts.currency_id', true).' IS NULL THEN 1 ELSE '.DB::getQueryGrammar()->wrap('accounts.currency_id', true).' END ELSE '.DB::getQueryGrammar()->wrap('clients.currency_id', true).' END'))
            ->get();
    }

    public function balances($accountId, $userId, $viewAll)
    {
        $select = DB::raw(
            'SUM('.DB::getQueryGrammar()->wrap('clients.balance', true).') as value, '
                  .DB::getQueryGrammar()->wrap('clients.currency_id', true).' as currency_id'
        );
        $balances = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->where('accounts.id', '=', $accountId)
            ->where('clients.is_deleted', '=', false)
            ->groupBy('accounts.id')
            ->groupBy(DB::raw('CASE WHEN '.DB::getQueryGrammar()->wrap('clients.currency_id', true).' IS NULL THEN CASE WHEN '.DB::getQueryGrammar()->wrap('accounts.currency_id', true).' IS NULL THEN 1 ELSE '.DB::getQueryGrammar()->wrap('accounts.currency_id', true).' END ELSE '.DB::getQueryGrammar()->wrap('clients.currency_id', true).' END'));

        if (!$viewAll) {
            $balances->where('clients.user_id', '=', $userId);
        }

        return $balances->get();
    }

    public function activities($accountId, $userId, $viewAll)
    {
        $activities = Activity::where('activities.account_id', '=', $accountId)
                ->where('activities.activity_type_id', '>', 0);

        if (!$viewAll){
            $activities = $activities->where('activities.user_id', '=', $userId);
        }

        return $activities->orderBy('activities.created_at', 'desc')
                ->with('client.contacts', 'user', 'invoice', 'payment', 'credit', 'account')
                ->take(50)
                ->get();
    }

    public function pastDue($accountId, $userId, $viewAll)
    {
        $pastDue = DB::table('invoices')
                    ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('invoices.account_id', '=', $accountId)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    ->where('invoices.quote_invoice_id', '=', null)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '<', date('Y-m-d'));

        if (!$viewAll){
            $pastDue = $pastDue->where('invoices.user_id', '=', $userId);
        }

        return $pastDue->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id', 'clients.user_id as client_user_id', 'invoice_type_id'])
                    ->orderBy('invoices.due_date', 'asc')
                    ->take(50)
                    ->get();
    }

    public function upcoming($accountId, $userId, $viewAll)
    {
        $upcoming = DB::table('invoices')
                    ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('invoices.account_id', '=', $accountId)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    ->where('invoices.quote_invoice_id', '=', null)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '>=', date('Y-m-d'))
                    ->orderBy('invoices.due_date', 'asc');

        if (!$viewAll){
            $upcoming = $upcoming->where('invoices.user_id', '=', $userId);
        }

        return $upcoming->take(50)
                    ->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id', 'clients.user_id as client_user_id', 'invoice_type_id'])
                    ->get();
    }

    public function payments($accountId, $userId, $viewAll)
    {
        $payments = DB::table('payments')
                    ->leftJoin('clients', 'clients.id', '=', 'payments.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->where('payments.account_id', '=', $accountId)
                    ->where('payments.is_deleted', '=', false)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('clients.is_deleted', '=', false)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true);

        if (!$viewAll){
            $payments = $payments->where('payments.user_id', '=', $userId);
        }

        return $payments->select(['payments.payment_date', 'payments.amount', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id', 'clients.user_id as client_user_id'])
                    ->orderBy('payments.payment_date', 'desc')
                    ->take(50)
                    ->get();
    }
}
