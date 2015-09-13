<?php namespace App\Http\Controllers;

use Auth;
use DB;
use View;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Payment;

class DashboardController extends BaseController
{
    public function index()
    {
        // total_income, billed_clients, invoice_sent and active_clients
        $select = DB::raw('COUNT(DISTINCT CASE WHEN invoices.id IS NOT NULL THEN clients.id ELSE null END) billed_clients,
                        SUM(CASE WHEN invoices.invoice_status_id >= '.INVOICE_STATUS_SENT.' THEN 1 ELSE 0 END) invoices_sent,
                        COUNT(DISTINCT clients.id) active_clients');
        $metrics = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->leftJoin('invoices', 'clients.id', '=', 'invoices.client_id')
            ->where('accounts.id', '=', Auth::user()->account_id)
            ->where('clients.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.is_recurring', '=', false)
            ->where('invoices.is_quote', '=', false)
            ->groupBy('accounts.id')
            ->first();

        $select = DB::raw('SUM(clients.paid_to_date) as value, clients.currency_id as currency_id');
        $paidToDate = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->where('accounts.id', '=', Auth::user()->account_id)
            ->where('clients.is_deleted', '=', false)
            ->groupBy('accounts.id')
            ->groupBy(DB::raw('CASE WHEN clients.currency_id IS NULL THEN CASE WHEN accounts.currency_id IS NULL THEN 1 ELSE accounts.currency_id END ELSE clients.currency_id END'))
            ->get();

        $select = DB::raw('AVG(invoices.amount) as invoice_avg, clients.currency_id as currency_id');
        $averageInvoice = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->leftJoin('invoices', 'clients.id', '=', 'invoices.client_id')
            ->where('accounts.id', '=', Auth::user()->account_id)
            ->where('clients.is_deleted', '=', false)
            ->where('invoices.is_deleted', '=', false)
            ->where('invoices.is_quote', '=', false)
            ->where('invoices.is_recurring', '=', false)
            ->groupBy('accounts.id')
            ->groupBy(DB::raw('CASE WHEN clients.currency_id IS NULL THEN CASE WHEN accounts.currency_id IS NULL THEN 1 ELSE accounts.currency_id END ELSE clients.currency_id END'))
            ->get();

        $select = DB::raw('SUM(clients.balance) as value, clients.currency_id as currency_id');
        $balances = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->where('accounts.id', '=', Auth::user()->account_id)
            ->where('clients.is_deleted', '=', false)
            ->groupBy('accounts.id')
            ->groupBy(DB::raw('CASE WHEN clients.currency_id IS NULL THEN CASE WHEN accounts.currency_id IS NULL THEN 1 ELSE accounts.currency_id END ELSE clients.currency_id END'))
            ->get();

        $activities = Activity::where('activities.account_id', '=', Auth::user()->account_id)
                ->where('activity_type_id', '>', 0)
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get();

        $pastDue = DB::table('invoices')
                    ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('invoices.account_id', '=', Auth::user()->account_id)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    //->where('invoices.is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '<', date('Y-m-d'))
                    ->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id'])
                    ->orderBy('invoices.due_date', 'asc')
                    ->take(50)
                    ->get();

        $upcoming = DB::table('invoices')
                    ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('invoices.account_id', '=', Auth::user()->account_id)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    //->where('invoices.is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '>=', date('Y-m-d'))
                    ->orderBy('invoices.due_date', 'asc')
                    ->take(50)
                    ->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id'])
                    ->get();

        $payments = DB::table('payments')
                    ->leftJoin('clients', 'clients.id', '=', 'payments.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->where('payments.account_id', '=', Auth::user()->account_id)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->select(['payments.payment_date', 'payments.amount', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id'])
                    ->orderBy('payments.id', 'desc')
                    ->take(50)
                    ->get();


        $data = [
            'account' => Auth::user()->account,
            'paidToDate' => $paidToDate,
            'balances' => $balances,
            'averageInvoice' => $averageInvoice,
            'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
            'activeClients' => $metrics ? $metrics->active_clients : 0,
            'activities' => $activities,
            'pastDue' => $pastDue,
            'upcoming' => $upcoming,
            'payments' => $payments,
            'title' => trans('texts.dashboard'),
        ];

        return View::make('dashboard', $data);
    }
}
