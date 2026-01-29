<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class BalanceSheetController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->companyId();
        $from = $request->query('from');
        $to   = $request->query('to');

        $accounts = ChartOfAccount::where('company_id', $companyId)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->leftJoin('journal_entries', 'chart_of_accounts.id', '=', 'journal_entries.chart_of_account_id')
            ->when($from, fn($q) => $q->whereDate('journal_entries.entry_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('journal_entries.entry_date', '<=', $to))
            ->select(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                DB::raw('COALESCE(SUM(journal_entries.debit - journal_entries.credit),0) as balance')
            )
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type'
            )
            ->orderBy('chart_of_accounts.code')
            ->get();

        return [
            'assets'      => $accounts->where('type', 'asset')->values(),
            'liabilities' => $accounts->where('type', 'liability')->values(),
            'equity'      => $accounts->where('type', 'equity')->values(),
        ];
    }

    public function csv(Request $request)
    {
        $companyId = auth()->user()->companyId();

        $rows = ChartOfAccount::where('company_id', $companyId)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->leftJoin('journal_entries', 'chart_of_accounts.id', '=', 'journal_entries.chart_of_account_id')
            ->select(
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                DB::raw('COALESCE(SUM(journal_entries.debit - journal_entries.credit),0) as balance')
            )
            ->groupBy(
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type'
            )
            ->orderBy('chart_of_accounts.code')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Code', 'Name', 'Type', 'Balance']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->code, $r->name, $r->type, $r->balance]);
            }
            fclose($out);
        }, 'balance_sheet.csv', ['Content-Type' => 'text/csv']);
    }
}
