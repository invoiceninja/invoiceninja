<?php

class DashboardController extends \BaseController {

  public function index()
  {
    // total_income, billed_clients, invoice_sent and active_clients
    $select = DB::raw('SUM(DISTINCT clients.paid_to_date) total_income, 
                        COUNT(DISTINCT CASE WHEN invoices.id IS NOT NULL THEN clients.id ELSE null END) billed_clients,
                        SUM(CASE WHEN invoices.invoice_status_id >= '.INVOICE_STATUS_SENT.' THEN 1 ELSE 0 END) invoices_sent,
                        COUNT(DISTINCT clients.id) active_clients');

    $metrics = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->leftJoin('invoices', 'clients.id', '=', 'invoices.client_id')
            ->where('accounts.id', '=', Auth::user()->account_id)
            ->where('clients.deleted_at', '=', null)
            ->groupBy('accounts.id')
            ->first();
    
    $invoiceAvg = DB::table('invoices')
                  ->where('invoices.account_id', '=', Auth::user()->account_id)
                  ->where('invoices.deleted_at', '=', null)
                  ->avg('amount');

    
    $activities = Activity::where('activities.account_id', '=', Auth::user()->account_id)
                ->orderBy('created_at', 'desc')->take(6)->get();

    $pastDue = Invoice::scope()->where('due_date', '<', date('Y-m-d'))
                ->orderBy('due_date', 'asc')->take(6)->get();

    $upcoming = Invoice::scope()->where('due_date', '>', date('Y-m-d'))
                  ->orderBy('due_date', 'asc')->take(6)->get();

    $data = [
      'totalIncome' => Utils::formatMoney($metrics->total_income, Session::get(SESSION_CURRENCY)),
      'billedClients' => $metrics->billed_clients,
      'invoicesSent' => $metrics->invoices_sent,
      'activeClients' => $metrics->active_clients,
      'invoiceAvg' => Utils::formatMoney($invoiceAvg, Session::get(SESSION_CURRENCY)),
      'activities' => $activities,
      'pastDue' => $pastDue,
      'upcoming' => $upcoming
    ];

    return View::make('dashboard', $data);
  }

}