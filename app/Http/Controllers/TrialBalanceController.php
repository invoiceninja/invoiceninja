<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

class TrialBalanceController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->companyId();
        $from = $request->query('from');
        $to   = $request->query('to');

        return ChartOfAccount::where('company_id', $companyId)
            ->leftJoin('journal_entries', 'chart_of_accounts.id', '=', 'journal_entries.chart_of_account_id')
            ->when($from, fn($q) => $q->whereDate('journal_entries.entry_date', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('journal_entries.entry_date', '<=', $to))
            ->select(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                DB::raw('COALESCE(SUM(journal_entries.debit),0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entries.credit),0) as total_credit')
            )
            ->groupBy(
                'chart_of_accounts.id',
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type'
            )
            ->orderBy('chart_of_accounts.code')
            ->get();
    }
    
    public function csv(Request $request)
    {
        $companyId = auth()->user()->companyId();

        $rows = ChartOfAccount::where('company_id', $companyId)
            ->leftJoin('journal_entries', 'chart_of_accounts.id', '=', 'journal_entries.chart_of_account_id')
            ->select(
                'chart_of_accounts.code',
                'chart_of_accounts.name',
                'chart_of_accounts.type',
                DB::raw('COALESCE(SUM(journal_entries.debit),0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entries.credit),0) as total_credit')
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
            fputcsv($out, ['Code', 'Name', 'Type', 'Debit', 'Credit']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->code,
                    $row->name,
                    $row->type,
                    $row->total_debit,
                    $row->total_credit,
                ]);
            }
            fclose($out);
        }, 'trial_balance.csv', ['Content-Type' => 'text/csv']);
    }
}
