<?php namespace App\Http\Controllers;

use Auth;
use DB;
use View;
use App\Models\Activity;
use App\Models\Invoice;

class DashboardController extends BaseController
{
    public function index()
    {
        // For Debuging : Need to remove when it is not longer needed. 
        // Auth::login(\App\Models\User::first());
        // dd(Auth::user());
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
            ->groupBy('accounts.id')
            ->groupBy(DB::raw('CASE WHEN clients.currency_id IS NULL THEN CASE WHEN accounts.currency_id IS NULL THEN 1 ELSE accounts.currency_id END ELSE clients.currency_id END'))
            ->get();



        $activities = Activity::where('activities.account_id', '=', Auth::user()->account_id)
                ->orderBy('created_at', 'desc')->take(6)->get();

        $pastDue = Invoice::scope()
                ->where('due_date', '<', date('Y-m-d'))
                ->where('balance', '>', 0)
                ->where('is_recurring', '=', false)
                ->where('is_quote', '=', false)
                ->where('is_deleted', '=', false)
                ->orderBy('due_date', 'asc')->take(6)->get();

        $upcoming = Invoice::scope()
                  ->where('due_date', '>=', date('Y-m-d'))
                  ->where('balance', '>', 0)
                  ->where('is_recurring', '=', false)
                  ->where('is_quote', '=', false)
                  ->where('is_deleted', '=', false)
                  ->orderBy('due_date', 'asc')->take(6)->get();

        $data = [
      'paidToDate' => $paidToDate,
      'averageInvoice' => $averageInvoice,
      'billedClients' => $metrics ? $metrics->billed_clients : 0,
      'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
      'activeClients' => $metrics ? $metrics->active_clients : 0,
      'activities' => $activities,
      'pastDue' => $pastDue,
      'upcoming' => $upcoming,
    ];

        return View::make('dashboard', $data);
    }
}
