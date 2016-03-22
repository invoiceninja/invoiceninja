<?php namespace App\Http\Controllers;

use Auth;
use DB;
use View;
use App\Models\Activity;

class DashboardApiController extends BaseAPIController
{
    public function index()
    {
        $view_all = !Auth::user()->hasPermission('view_all');
        $user_id = Auth::user()->id;

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
            ->where('invoices.is_quote', '=', false);

        if(!$view_all){
            $metrics = $metrics->where(function($query) use($user_id){
                $query->where('invoices.user_id', '=', $user_id);
                $query->orwhere(function($query) use($user_id){
                    $query->where('invoices.user_id', '=', null);
                    $query->where('clients.user_id', '=', $user_id);
                });
            });
        }

        $metrics = $metrics->groupBy('accounts.id')
            ->first();

        $select = DB::raw('SUM(clients.paid_to_date) as value, clients.currency_id as currency_id');
        $paidToDate = DB::table('accounts')
            ->select($select)
            ->leftJoin('clients', 'accounts.id', '=', 'clients.account_id')
            ->where('accounts.id', '=', Auth::user()->account_id)
            ->where('clients.is_deleted', '=', false);

        if(!$view_all){
            $paidToDate = $paidToDate->where('clients.user_id', '=', $user_id);
        }

        $paidToDate = $paidToDate->groupBy('accounts.id')
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
            ->where('invoices.is_recurring', '=', false);

        if(!$view_all){
            $averageInvoice = $averageInvoice->where('invoices.user_id', '=', $user_id);
        }

        $averageInvoice = $averageInvoice->groupBy('accounts.id')
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
                    ->where('invoices.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '<', date('Y-m-d'));

        if(!$view_all){
            $pastDue = $pastDue->where('invoices.user_id', '=', $user_id);
        }

        $pastDue = $pastDue->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id', 'clients.user_id as client_user_id', 'is_quote'])
                    ->orderBy('invoices.due_date', 'asc')
                    ->take(50)
                    ->get();

        $upcoming = DB::table('invoices')
                    ->leftJoin('clients', 'clients.id', '=', 'invoices.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->where('invoices.account_id', '=', Auth::user()->account_id)
                    ->where('clients.deleted_at', '=', null)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('invoices.deleted_at', '=', null)
                    ->where('invoices.is_recurring', '=', false)
                    //->where('invoices.is_quote', '=', false)
                    ->where('invoices.balance', '>', 0)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('contacts.is_primary', '=', true)
                    ->where('invoices.due_date', '>=', date('Y-m-d'))
                    ->orderBy('invoices.due_date', 'asc');

        if(!$view_all){
            $upcoming = $upcoming->where('invoices.user_id', '=', $user_id);
        }

        $upcoming = $upcoming->take(50)
                    ->select(['invoices.due_date', 'invoices.balance', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id', 'clients.user_id as client_user_id', 'is_quote'])
                    ->get();

        $payments = DB::table('payments')
                    ->leftJoin('clients', 'clients.id', '=', 'payments.client_id')
                    ->leftJoin('contacts', 'contacts.client_id', '=', 'clients.id')
                    ->leftJoin('invoices', 'invoices.id', '=', 'payments.invoice_id')
                    ->where('payments.account_id', '=', Auth::user()->account_id)
                    ->where('payments.is_deleted', '=', false)
                    ->where('invoices.is_deleted', '=', false)
                    ->where('clients.is_deleted', '=', false)
                    ->where('contacts.deleted_at', '=', null)
                    ->where('contacts.is_primary', '=', true);

        if(!$view_all){
            $payments = $payments->where('payments.user_id', '=', $user_id);
        }

        $payments = $payments->select(['payments.payment_date', 'payments.amount', 'invoices.public_id', 'invoices.invoice_number', 'clients.name as client_name', 'contacts.email', 'contacts.first_name', 'contacts.last_name', 'clients.currency_id', 'clients.public_id as client_public_id', 'clients.user_id as client_user_id'])
                    ->orderBy('payments.payment_date', 'desc')
                    ->take(50)
                    ->get();

        $hasQuotes = false;
        foreach ([$upcoming, $pastDue] as $data) {
            foreach ($data as $invoice) {
                if ($invoice->is_quote) {
                    $hasQuotes = true;
                }
            }
        }


        $data = [
                'id' => 1,
                'paidToDate' => $paidToDate[0]->value,
                'paidToDateCurrency' => $paidToDate[0]->currency_id,
                'balances' => $balances[0]->value,
                'balancesCurrency' => $balances[0]->currency_id,
                'averageInvoice' => $averageInvoice[0]->invoice_avg,
                'averageInvoiceCurrency' => $averageInvoice[0]->currency_id,
                'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
                'activeClients' => $metrics ? $metrics->active_clients : 0,
            ];



            return $this->response($data);

    }
}
